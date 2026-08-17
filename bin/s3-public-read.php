<?php
/**
 * GRANT ANONYMOUS READ ON THE PRODUCT IMAGE PREFIX
 * ================================================
 *     php bin/s3-public-read.php --show     # print the current policy
 *     php bin/s3-public-read.php --apply    # allow public GET on products/*
 *     php bin/s3-public-read.php --revoke   # remove the policy again
 *
 * Railway's bucket UI exposes only region and deletion, so public read has to
 * be set through the S3 API. Tigris is S3-compatible and accepts a bucket
 * policy; whether it honours one is what --apply finds out.
 *
 * SCOPED TO THE PREFIX, NOT THE BUCKET
 * ------------------------------------
 * The policy grants s3:GetObject on `products/*` only. Making the whole bucket
 * readable would also expose anything stored there later by something else,
 * and "public" is much harder to walk back than to grant. Product images are
 * catalogue art shown to every visitor, so that prefix is genuinely public;
 * nothing else is assumed to be.
 *
 * Read is granted, never write or list. Anonymous write is how buckets become
 * someone else's file host, and anonymous list turns "you need the URL" into
 * "here is everything".
 *
 * CLI ONLY.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("This is a maintenance script and cannot be run over HTTP.\n");
}

require_once __DIR__ . '/../bootstrap/api.php';

$cfg = s3_config();

if (!$cfg['enabled']) {
    fwrite(STDERR, "Object storage is not configured.\n");
    exit(1);
}

/**
 * Sign and send a request that carries a query string.
 *
 * lib/s3.php deliberately signs no query string -- the four calls it makes do
 * not need one. Bucket-level operations address themselves with `?policy`,
 * which must appear in the canonical request or the signature will not match,
 * so this is a local extension rather than a change to the shared client.
 */
function s3_bucket_request($method, $query, $body, array $cfg)
{
    $parts = parse_url($cfg['endpoint']);
    $host  = $parts['host'] . (isset($parts['port']) ? ':' . $parts['port'] : '');
    $path  = '/' . rawurlencode($cfg['bucket']);
    $url   = $cfg['endpoint'] . $path . '?' . $query;

    $now         = gmdate('Ymd\THis\Z');
    $shortDate   = substr($now, 0, 8);
    $payloadHash = hash('sha256', $body);

    $headers = [
        'host'                 => $host,
        'x-amz-content-sha256' => $payloadHash,
        'x-amz-date'           => $now,
    ];

    if ($body !== '') {
        $headers['content-type'] = 'application/json';
    }

    ksort($headers);

    $canonicalHeaders = '';
    foreach ($headers as $name => $value) {
        $canonicalHeaders .= $name . ':' . $value . "\n";
    }

    $signedHeaderList = implode(';', array_keys($headers));

    // A valueless query parameter still needs its '=' in the canonical form.
    $canonicalQuery = strpos($query, '=') === false ? $query . '=' : $query;

    $canonicalRequest = implode("\n", [
        strtoupper($method),
        $path,
        $canonicalQuery,
        $canonicalHeaders,
        $signedHeaderList,
        $payloadHash,
    ]);

    $scope = $shortDate . '/' . $cfg['region'] . '/s3/aws4_request';

    $stringToSign = implode("\n", [
        'AWS4-HMAC-SHA256',
        $now,
        $scope,
        hash('sha256', $canonicalRequest),
    ]);

    $signature = hash_hmac('sha256', $stringToSign, s3_signing_key($cfg['secret'], $shortDate, $cfg['region']));

    $curlHeaders = [
        'Authorization: AWS4-HMAC-SHA256 Credential=' . $cfg['key'] . '/' . $scope
            . ', SignedHeaders=' . $signedHeaderList . ', Signature=' . $signature,
        'x-amz-content-sha256: ' . $payloadHash,
        'x-amz-date: ' . $now,
    ];

    if ($body !== '') {
        $curlHeaders[] = 'Content-Type: application/json';
    }

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $url,
        CURLOPT_CUSTOMREQUEST  => strtoupper($method),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => $curlHeaders,
        CURLOPT_TIMEOUT        => 20,
    ]);

    if ($body !== '') {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    }

    $response = curl_exec($ch);
    $status   = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error    = curl_error($ch);
    curl_close($ch);

    return ['status' => $status, 'body' => (string) $response, 'error' => $error];
}

$prefix = trim((string) env('S3_PREFIX', 'products'), '/');

$policy = json_encode([
    'Version'   => '2012-10-17',
    'Statement' => [[
        'Sid'       => 'PublicReadProductImages',
        'Effect'    => 'Allow',
        'Principal' => '*',
        // Read only. No PutObject, no ListBucket.
        'Action'    => ['s3:GetObject'],
        'Resource'  => ['arn:aws:s3:::' . $cfg['bucket'] . '/' . $prefix . '/*'],
    ]],
], JSON_UNESCAPED_SLASHES);

echo "\nBucket: {$cfg['bucket']} at {$cfg['endpoint']}\n";
echo "Prefix: {$prefix}/\n\n";

if (in_array('--show', $argv, true)) {
    $r = s3_bucket_request('GET', 'policy', '', $cfg);
    echo "  GET ?policy -> HTTP {$r['status']}\n";
    echo '  ' . ($r['body'] === '' ? '(empty)' : substr($r['body'], 0, 600)) . "\n\n";
    exit($r['status'] === 200 ? 0 : 1);
}

if (in_array('--revoke', $argv, true)) {
    $r = s3_bucket_request('DELETE', 'policy', '', $cfg);
    echo "  DELETE ?policy -> HTTP {$r['status']}\n";
    echo '  ' . substr($r['body'], 0, 400) . "\n\n";
    exit($r['status'] < 300 ? 0 : 1);
}

if (!in_array('--apply', $argv, true)) {
    echo "  Nothing to do. Pass --show, --apply or --revoke.\n\n";
    echo "  The policy --apply would set:\n";
    echo '  ' . $policy . "\n\n";
    exit(0);
}

$r = s3_bucket_request('PUT', 'policy', $policy, $cfg);

echo "  PUT ?policy -> HTTP {$r['status']}\n";

if ($r['error'] !== '') {
    echo "  transport error: {$r['error']}\n\n";
    exit(1);
}

if ($r['status'] >= 300) {
    echo '  ' . substr($r['body'], 0, 500) . "\n\n";
    echo "  This endpoint does not accept a bucket policy. Use\n";
    echo "  STORAGE_URL_MODE=presign instead -- it needs no bucket change.\n\n";
    exit(1);
}

echo "  applied.\n\n";

// Prove it from the outside: an unsigned GET is the only test that matters.
$probe = s3_object_url($cfg, $prefix . '/1774940539_party-wings.webp');
$ch = curl_init($probe);
curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 20]);
curl_exec($ch);
$code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "  Anonymous GET of a product image -> HTTP {$code}\n";
echo $code === 200
    ? "  Public read is live. STORAGE_URL_MODE=public will now work.\n\n"
    : "  Still not readable anonymously; the policy was accepted but is not in effect.\n\n";

exit($code === 200 ? 0 : 1);
