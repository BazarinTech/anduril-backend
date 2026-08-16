<?php
/**
 * IMAGE PROXY
 * ===========
 * Serves a product image from object storage through this server:
 *
 *     GET /backend/mains/image.php?f=1774940539_party-wings.webp
 *
 * Only used when STORAGE_URL_MODE=proxy. It exists for a bucket that is not
 * publicly readable but where stable, non-expiring URLs are wanted -- presigned
 * URLs expire, which makes them awkward to cache or paste into anything.
 *
 * The trade-off is that every image load becomes a PHP request. If the bucket
 * can be public, 'public' mode is faster and cheaper; this is the fallback for
 * when it cannot.
 *
 * WHAT IS NOT TRUSTED
 * -------------------
 * `f` is a filename from the query string, so it is reduced to its basename
 * and matched against a strict pattern before being used as an object key.
 * Without that, `?f=../../config/env.php` is a path traversal -- and against
 * object storage, `?f=../` style keys can address objects outside the prefix.
 */

require_once __DIR__ . '/../../bootstrap/api.php';

$requested = (string) ($_GET['f'] ?? '');

// basename() first, then an allowlist. Either alone is weaker than both.
$filename = basename($requested);

if ($filename === '' || !preg_match('/^[A-Za-z0-9._-]+$/', $filename) || strpos($filename, '..') !== false) {
    http_response_code(400);
    header('Content-Type: text/plain');
    exit('Invalid image reference.');
}

if (storage_driver() !== 's3') {
    // Nothing to proxy: the file is on this disk and the web server can serve
    // it directly, which is what 'local' mode's URLs already point at.
    http_response_code(404);
    header('Content-Type: text/plain');
    exit('Not found.');
}

$result = s3_request('GET', storage_prefix() . $filename);

if (!$result['ok']) {
    http_response_code($result['status'] === 404 ? 404 : 502);
    header('Content-Type: text/plain');
    exit($result['status'] === 404 ? 'Not found.' : 'Image temporarily unavailable.');
}

/**
 * Carry the object's own content type through. Guessing from the extension
 * would mislabel anything renamed, and browsers act on the header.
 */
$contentType = 'application/octet-stream';
if (preg_match('/^content-type:\s*(.+)$/im', $result['headers'], $m)) {
    $contentType = trim($m[1]);
}

// Keys carry a timestamp and are never rewritten, so this can be cached hard.
header('Content-Type: ' . $contentType);
header('Content-Length: ' . strlen($result['body']));
header('Cache-Control: public, max-age=31536000, immutable');

// This is the one endpoint that returns bytes rather than JSON; the CORS
// header bootstrap already sent applies to it just the same.
echo $result['body'];
