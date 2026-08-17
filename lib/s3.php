<?php
/**
 * MINIMAL S3 CLIENT (SIGNATURE V4)
 * ================================
 * Enough of the S3 API to put, fetch, delete and presign an object. Written
 * against the protocol rather than pulled in as a dependency, for two reasons:
 *
 *   - aws/aws-sdk-php is ~15MB and drags in Guzzle, PSR-7 and promises to do
 *     what four requests need.
 *   - `composer install` is not safe in this tree. The vendored
 *     bazarin/bazarin-php-library is hand-patched under a version number that
 *     does not describe it, so running composer would overwrite those fixes.
 *     Adding a dependency the normal way is not currently an option.
 *
 * Everything here needs only curl, openssl and hash, all of which are present.
 *
 * WORKS WITH ANY S3-COMPATIBLE ENDPOINT, which is what Railway's Bucket is.
 * Set S3_ENDPOINT to the host it gives you; path-style addressing is used by
 * default because self-hosted endpoints rarely have wildcard DNS for
 * virtual-host style (bucket.endpoint).
 *
 * SIGNING
 * -------
 * AWS Signature Version 4, payload-signed. The canonical request, string to
 * sign, and signing key derivation follow the published algorithm exactly --
 * see the test vectors in tests/s3-signature-test.php, which check this
 * implementation against AWS's own documented example.
 */

if (!function_exists('s3_config')) {
    /**
     * @return array{
     *   enabled:bool, key:string, secret:string, bucket:string,
     *   endpoint:string, region:string, path_style:bool, public_base:string
     * }
     */
    function s3_config()
    {
        $endpoint = rtrim((string) env('S3_ENDPOINT', ''), '/');
        $key      = (string) env('S3_ACCESS_KEY_ID', '');
        $secret   = (string) env('S3_SECRET_ACCESS_KEY', '');
        $bucket   = (string) env('S3_BUCKET', '');

        return [
            // All four are required. A half-configured bucket must not look
            // enabled, or uploads succeed locally and vanish on deploy.
            'enabled'     => $endpoint !== '' && $key !== '' && $secret !== '' && $bucket !== '',
            'key'         => $key,
            'secret'      => $secret,
            'bucket'      => $bucket,
            'endpoint'    => $endpoint,
            'region'      => (string) env('S3_REGION', 'us-east-1'),
            'path_style'  => (string) env('S3_PATH_STYLE', '1') === '1',
            // Where objects are publicly readable, if they are. Empty means
            // they are not, and URLs have to be presigned.
            'public_base' => rtrim((string) env('S3_PUBLIC_BASE_URL', ''), '/'),
        ];
    }
}

if (!function_exists('s3_object_url')) {
    /** The URL an object lives at, before signing. */
    function s3_object_url(array $cfg, $key)
    {
        $encoded = s3_encode_path($key);

        if ($cfg['path_style']) {
            return $cfg['endpoint'] . '/' . rawurlencode($cfg['bucket']) . $encoded;
        }

        $parts = parse_url($cfg['endpoint']);
        $scheme = $parts['scheme'] ?? 'https';
        $host   = $parts['host'] ?? '';
        $port   = isset($parts['port']) ? ':' . $parts['port'] : '';

        return $scheme . '://' . $cfg['bucket'] . '.' . $host . $port . $encoded;
    }
}

if (!function_exists('s3_encode_path')) {
    /**
     * Percent-encode each path segment, leaving the separators alone.
     *
     * rawurlencode() on the whole key would escape the slashes and address a
     * single object whose name contains them.
     */
    function s3_encode_path($key)
    {
        $segments = explode('/', ltrim((string) $key, '/'));

        return '/' . implode('/', array_map('rawurlencode', $segments));
    }
}

if (!function_exists('s3_signing_key')) {
    /**
     * Derive the request signing key: four chained HMACs over the date,
     * region, service and terminator. Binary output at every step -- hex here
     * produces a signature that is well-formed and always rejected.
     */
    function s3_signing_key($secret, $date, $region, $service = 's3')
    {
        $kDate    = hash_hmac('sha256', $date, 'AWS4' . $secret, true);
        $kRegion  = hash_hmac('sha256', $region, $kDate, true);
        $kService = hash_hmac('sha256', $service, $kRegion, true);

        return hash_hmac('sha256', 'aws4_request', $kService, true);
    }
}

if (!function_exists('s3_sign_v4')) {
    /**
     * Build the Authorization header for one request.
     *
     * Split out from s3_request() and given an explicit timestamp so it can be
     * checked against AWS's own published examples -- a signing routine that
     * is only exercised by live calls is one whose failures all look like
     * "SignatureDoesNotMatch" with no way to tell which step was wrong.
     * See tests/s3-signature-test.php.
     *
     * @return array{authorization:string, signature:string, canonical_request:string,
     *               string_to_sign:string, signed_headers:string, headers:array}
     */
    function s3_sign_v4($method, $path, array $headers, $payloadHash, $timestamp, $region, $accessKey, $secret)
    {
        $shortDate = substr($timestamp, 0, 8);

        // Header names are lower-cased and sorted for the canonical request.
        $signed = array_change_key_case($headers, CASE_LOWER);
        ksort($signed);

        $canonicalHeaders = '';
        foreach ($signed as $name => $value) {
            // Values are trimmed and inner runs of whitespace collapsed, per
            // the spec. Skipping this breaks any header with padding in it.
            $canonicalHeaders .= $name . ':' . preg_replace('/\s+/', ' ', trim((string) $value)) . "\n";
        }

        $signedHeaderList = implode(';', array_keys($signed));

        $canonicalRequest = implode("\n", [
            strtoupper($method),
            $path,
            '',                 // no query string on any call this client makes
            $canonicalHeaders,
            $signedHeaderList,
            $payloadHash,
        ]);

        $scope = $shortDate . '/' . $region . '/s3/aws4_request';

        $stringToSign = implode("\n", [
            'AWS4-HMAC-SHA256',
            $timestamp,
            $scope,
            hash('sha256', $canonicalRequest),
        ]);

        $signature = hash_hmac(
            'sha256',
            $stringToSign,
            s3_signing_key($secret, $shortDate, $region),
            false
        );

        return [
            'authorization' => 'AWS4-HMAC-SHA256 '
                . 'Credential=' . $accessKey . '/' . $scope . ', '
                . 'SignedHeaders=' . $signedHeaderList . ', '
                . 'Signature=' . $signature,
            'signature'         => $signature,
            'canonical_request' => $canonicalRequest,
            'string_to_sign'    => $stringToSign,
            'signed_headers'    => $signedHeaderList,
            'headers'           => $signed,
        ];
    }
}

if (!function_exists('s3_request')) {
    /**
     * Sign and send one S3 request.
     *
     * @param string $method  GET | PUT | DELETE | HEAD
     * @param string $key     Object key, e.g. 'products/1774940539_wings.webp'
     * @param string $body    Request body for PUT.
     * @param array  $headers Extra headers, e.g. ['Content-Type' => 'image/webp'].
     *
     * @return array{ok:bool, status:int, body:string, headers:string, error:string|null}
     */
    function s3_request($method, $key, $body = '', array $headers = [], array $cfg = null)
    {
        $cfg = $cfg ?? s3_config();

        if (!$cfg['enabled']) {
            return ['ok' => false, 'status' => 0, 'body' => '', 'headers' => '', 'error' => 'Object storage is not configured'];
        }

        $url   = s3_object_url($cfg, $key);
        $parts = parse_url($url);
        $host  = $parts['host'] . (isset($parts['port']) ? ':' . $parts['port'] : '');
        $path  = $parts['path'] ?? '/';

        $now         = gmdate('Ymd\THis\Z');
        $payloadHash = hash('sha256', $body);

        $headers['host']                 = $host;
        $headers['x-amz-content-sha256'] = $payloadHash;
        $headers['x-amz-date']           = $now;

        $sig = s3_sign_v4(
            $method, $path, $headers, $payloadHash, $now,
            $cfg['region'], $cfg['key'], $cfg['secret']
        );

        $curlHeaders = ['Authorization: ' . $sig['authorization']];
        foreach ($sig['headers'] as $name => $value) {
            if ($name === 'host') {
                continue; // curl sets this itself
            }
            $curlHeaders[] = $name . ': ' . $value;
        }

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_CUSTOMREQUEST  => strtoupper($method),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER         => true,
            CURLOPT_HTTPHEADER     => $curlHeaders,
            // Uploads are small (a 500x500 image), and a stalled bucket must
            // not hold an admin request open for the curl default of forever.
            CURLOPT_TIMEOUT        => (int) env('S3_TIMEOUT', 20),
            CURLOPT_CONNECTTIMEOUT => 5,
        ]);

        if (strtoupper($method) === 'PUT') {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }

        if (strtoupper($method) === 'HEAD') {
            curl_setopt($ch, CURLOPT_NOBODY, true);
        }

        $raw      = curl_exec($ch);
        $err      = curl_error($ch);
        $status   = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSz = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        curl_close($ch);

        if ($raw === false || $err !== '') {
            error_log('[s3] ' . $method . ' ' . $key . ' failed: ' . $err);

            return ['ok' => false, 'status' => 0, 'body' => '', 'headers' => '', 'error' => 'Storage unreachable'];
        }

        $responseHeaders = substr($raw, 0, $headerSz);
        $responseBody    = substr($raw, $headerSz);

        if ($status < 200 || $status >= 300) {
            // S3 errors are XML with a <Message>; surface that rather than a
            // bare status code, because "SignatureDoesNotMatch" and
            // "NoSuchBucket" need completely different fixes.
            $reason = '';
            if (preg_match('#<Message>(.*?)</Message>#s', $responseBody, $m)) {
                $reason = $m[1];
            } elseif (preg_match('#<Code>(.*?)</Code>#s', $responseBody, $m)) {
                $reason = $m[1];
            }

            /**
             * A 404 from HEAD is how "is this object there?" answers no. It
             * is a normal outcome of s3_exists(), so logging it as an error
             * buries real failures under one line per file during a migration.
             */
            if (!(strtoupper($method) === 'HEAD' && $status === 404)) {
                error_log('[s3] ' . $method . ' ' . $key . ' -> HTTP ' . $status . ' ' . $reason);
            }

            return [
                'ok'      => false,
                'status'  => $status,
                'body'    => $responseBody,
                'headers' => $responseHeaders,
                'error'   => $reason !== '' ? $reason : ('HTTP ' . $status),
            ];
        }

        return ['ok' => true, 'status' => $status, 'body' => $responseBody, 'headers' => $responseHeaders, 'error' => null];
    }
}

if (!function_exists('s3_put')) {
    /** Upload an object. Returns true on success. */
    function s3_put($key, $body, $contentType = 'application/octet-stream', array $cfg = null)
    {
        $result = s3_request('PUT', $key, $body, [
            'Content-Type'   => $contentType,
            'Content-Length' => (string) strlen($body),
            // Long cache: the key carries a timestamp, so a given key's
            // contents never change. Replacing an image mints a new key.
            'Cache-Control'  => 'public, max-age=31536000, immutable',
        ], $cfg);

        return $result['ok'];
    }
}

if (!function_exists('s3_delete')) {
    function s3_delete($key, array $cfg = null)
    {
        $result = s3_request('DELETE', $key, '', [], $cfg);

        // S3 answers 204 for a key that was never there. That is the outcome
        // we wanted either way.
        return $result['ok'] || $result['status'] === 404;
    }
}

if (!function_exists('s3_exists')) {
    function s3_exists($key, array $cfg = null)
    {
        return s3_request('HEAD', $key, '', [], $cfg)['ok'];
    }
}

if (!function_exists('s3_presign')) {
    /**
     * A time-limited GET URL, for buckets that are not publicly readable.
     *
     * Query-string signing rather than header signing, so the URL can go
     * straight into an <img src>.
     *
     * STABLE WITHIN A WINDOW -- THIS IS THE WHOLE POINT
     * -------------------------------------------------
     * The signing timestamp is floored to S3_PRESIGN_WINDOW rather than taken
     * from the clock, so every call within the same window produces a
     * byte-identical URL.
     *
     * Signing at "now" seems harmless and is not. The signature changes on
     * every render, so the URL changes, so the browser's cache key changes --
     * and a cache that never hits is a cache that does not exist. Product
     * images were being re-downloaded in full on every page load, at roughly
     * two seconds each against the bucket, for files of about 20KB. The
     * latency was never the file size; it was fetching them again and again.
     *
     * The same reasoning applies to any CDN in front, and to Next.js's image
     * optimiser, which keys its cache on the source URL.
     *
     * Validity spans the window *plus* the requested lifetime, so a URL minted
     * at the very end of a window is still good for the full duration.
     */
    function s3_presign($key, $expiresIn = 86400, array $cfg = null)
    {
        $cfg = $cfg ?? s3_config();

        if (!$cfg['enabled']) {
            return '';
        }

        $url   = s3_object_url($cfg, $key);
        $parts = parse_url($url);
        $host  = $parts['host'] . (isset($parts['port']) ? ':' . $parts['port'] : '');
        $path  = $parts['path'] ?? '/';

        /**
         * A day by default, aligned with the image optimiser's cache TTL.
         *
         * Shorter windows rotate the URL more often, and every rotation is a
         * cache miss for the browser, any CDN, and Next's optimiser -- which
         * means another 1.4-9s round trip to the bucket for a file that has
         * not changed. Longer windows mean a given URL is shareable for
         * longer, which for public catalogue art is not a meaningful exposure.
         */
        $window = max(60, (int) env('S3_PRESIGN_WINDOW', 86400));
        $anchor = intdiv(time(), $window) * $window;

        // AWS and compatible endpoints cap this at seven days.
        $expiresIn = min($expiresIn + $window, 604800);

        $now       = gmdate('Ymd\THis\Z', $anchor);
        $shortDate = substr($now, 0, 8);
        $scope     = $shortDate . '/' . $cfg['region'] . '/s3/aws4_request';

        $query = [
            'X-Amz-Algorithm'     => 'AWS4-HMAC-SHA256',
            'X-Amz-Credential'    => $cfg['key'] . '/' . $scope,
            'X-Amz-Date'          => $now,
            'X-Amz-Expires'       => (string) $expiresIn,
            'X-Amz-SignedHeaders' => 'host',
        ];

        ksort($query);

        $canonicalQuery = [];
        foreach ($query as $name => $value) {
            $canonicalQuery[] = rawurlencode($name) . '=' . rawurlencode($value);
        }
        $canonicalQuery = implode('&', $canonicalQuery);

        $canonicalRequest = implode("\n", [
            'GET',
            $path,
            $canonicalQuery,
            'host:' . $host . "\n",
            'host',
            'UNSIGNED-PAYLOAD',
        ]);

        $stringToSign = implode("\n", [
            'AWS4-HMAC-SHA256',
            $now,
            $scope,
            hash('sha256', $canonicalRequest),
        ]);

        $signature = hash_hmac(
            'sha256',
            $stringToSign,
            s3_signing_key($cfg['secret'], $shortDate, $cfg['region']),
            false
        );

        return $url . '?' . $canonicalQuery . '&X-Amz-Signature=' . $signature;
    }
}
