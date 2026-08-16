<?php
/**
 * SIGNATURE V4 KNOWN-ANSWER TESTS
 * ===============================
 *     php tests/s3-signature-test.php
 *
 * Checks lib/s3.php against the worked examples AWS publishes in
 * "Signature Version 4 Test Suite" and the S3 authentication documentation.
 * Those give the expected canonical request, string to sign and final
 * signature for a fixed key, region and timestamp.
 *
 * This matters more than usual here, because the signing code is hand-written
 * rather than taken from an SDK. Every mistake in it -- a hex digest where a
 * binary one belongs, an unsorted header, a missing trailing newline --
 * produces exactly one symptom from the server: 403 SignatureDoesNotMatch.
 * There is no way to tell those apart from the outside, so they are pinned
 * from the inside instead.
 */

// s3_config() reads env(); the tests never call it, but the file expects the
// function to exist.
if (!function_exists('env')) {
    function env($key, $default = null) { return $default; }
}

require_once __DIR__ . '/../lib/s3.php';

$pass = 0;
$fail = 0;

function check($label, $actual, $expected)
{
    global $pass, $fail;

    if ($actual === $expected) {
        printf("  PASS  %s\n", $label);
        $pass++;
        return;
    }

    printf("  FAIL  %s\n", $label);
    printf("        expected: %s\n", var_export($expected, true));
    printf("        actual:   %s\n", var_export($actual, true));
    $fail++;
}

/* ------------------------------------------------------------------------
 * AWS documented example: GET Object
 *
 * From "Examples of the Complete Version 4 Signing Process (Python)" /
 * "Authenticating Requests: Using the Authorization Header (AWS Signature
 * Version 4)". Bucket `examplebucket`, object `test.txt`, ranged GET.
 * ---------------------------------------------------------------------- */

$accessKey = 'AKIAIOSFODNN7EXAMPLE';
$secret    = 'wJalrXUtnFEMI/K7MDENG/bPxRfiCYEXAMPLEKEY';
$region    = 'us-east-1';
$timestamp = '20130524T000000Z';

// The empty-payload hash, which AWS's example uses.
$emptyHash = 'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855';

echo "\n== Empty payload hash ==\n";
check('sha256("") matches the documented constant', hash('sha256', ''), $emptyHash);

echo "\n== Signing key derivation ==\n";
/**
 * The four-step HMAC chain, pinned so a change to it is visible here rather
 * than only as a 403 from the server.
 *
 * This constant is not independently sourced -- it is what the derivation
 * produces. What makes it trustworthy is the GET and PUT signature checks
 * below: both are AWS's own published values, and neither can match unless
 * this key is byte-for-byte correct, since the signature is an HMAC *under*
 * it. So the derivation is verified by those, and recorded here so a later
 * edit that breaks it says which step went wrong instead of just failing.
 */
$derived = bin2hex(s3_signing_key($secret, '20130524', $region));
check(
    'kSigning for 20130524/us-east-1/s3',
    $derived,
    'dbb893acc010964918f1fd433add87c70e8b0db6be30c1fbeafefa5ec6ba8378'
);

echo "\n== GET Object with a Range header ==\n";

$sig = s3_sign_v4(
    'GET',
    '/test.txt',
    [
        'host'                 => 'examplebucket.s3.amazonaws.com',
        'range'                => 'bytes=0-9',
        'x-amz-content-sha256' => $emptyHash,
        'x-amz-date'           => $timestamp,
    ],
    $emptyHash,
    $timestamp,
    $region,
    $accessKey,
    $secret
);

check(
    'canonical request',
    $sig['canonical_request'],
    "GET\n"
    . "/test.txt\n"
    . "\n"
    . "host:examplebucket.s3.amazonaws.com\n"
    . "range:bytes=0-9\n"
    . "x-amz-content-sha256:{$emptyHash}\n"
    . "x-amz-date:20130524T000000Z\n"
    . "\n"
    . "host;range;x-amz-content-sha256;x-amz-date\n"
    . $emptyHash
);

check(
    'string to sign',
    $sig['string_to_sign'],
    "AWS4-HMAC-SHA256\n"
    . "20130524T000000Z\n"
    . "20130524/us-east-1/s3/aws4_request\n"
    . '7344ae5b7ee6c3e7e6b0fe0640412a37625d1fbfff95c48bbb2dc43964946972'
);

check(
    'signature',
    $sig['signature'],
    'f0e8bdb87c964420e857bd35b5d6ed310bd44f0170aba48dd91039c6036bdb41'
);

check(
    'authorization header',
    $sig['authorization'],
    'AWS4-HMAC-SHA256 Credential=AKIAIOSFODNN7EXAMPLE/20130524/us-east-1/s3/aws4_request, '
    . 'SignedHeaders=host;range;x-amz-content-sha256;x-amz-date, '
    . 'Signature=f0e8bdb87c964420e857bd35b5d6ed310bd44f0170aba48dd91039c6036bdb41'
);

/* ------------------------------------------------------------------------
 * AWS documented example: PUT Object
 *
 * Object key `test$file.text`, which also exercises escaping, plus a
 * non-empty payload and a header AWS sorts into the middle.
 * ---------------------------------------------------------------------- */

echo "\n== PUT Object with a non-empty body ==\n";

$putPayloadHash = '44ce7dd67c959e0d3524ffac1771dfbba87d2b6b4b4e99e42034a8b803f8b072';
check('sha256("Welcome to Amazon S3.")', hash('sha256', 'Welcome to Amazon S3.'), $putPayloadHash);

$sig = s3_sign_v4(
    'PUT',
    '/test%24file.text',
    [
        'date'                 => 'Fri, 24 May 2013 00:00:00 GMT',
        'host'                 => 'examplebucket.s3.amazonaws.com',
        'x-amz-content-sha256' => $putPayloadHash,
        'x-amz-date'           => $timestamp,
        'x-amz-storage-class'  => 'REDUCED_REDUNDANCY',
    ],
    $putPayloadHash,
    $timestamp,
    $region,
    $accessKey,
    $secret
);

check(
    'signature',
    $sig['signature'],
    '98ad721746da40c64f1a55b78f14c238d841ea1380cd77a1b5971af0ece108bd'
);

/* ------------------------------------------------------------------------
 * Behaviour this implementation has to get right regardless of AWS's
 * examples.
 * ---------------------------------------------------------------------- */

echo "\n== Header handling ==\n";

$mixed = s3_sign_v4(
    'GET', '/x',
    ['Host' => 'h', 'X-Amz-Date' => $timestamp, 'x-amz-content-sha256' => $emptyHash],
    $emptyHash, $timestamp, $region, $accessKey, $secret
);
$lower = s3_sign_v4(
    'GET', '/x',
    ['host' => 'h', 'x-amz-date' => $timestamp, 'x-amz-content-sha256' => $emptyHash],
    $emptyHash, $timestamp, $region, $accessKey, $secret
);
check('header case does not change the signature', $mixed['signature'], $lower['signature']);

$unsorted = s3_sign_v4(
    'GET', '/x',
    ['x-amz-date' => $timestamp, 'x-amz-content-sha256' => $emptyHash, 'host' => 'h'],
    $emptyHash, $timestamp, $region, $accessKey, $secret
);
check('header order does not change the signature', $unsorted['signature'], $lower['signature']);
check('signed header list is sorted', $lower['signed_headers'], 'host;x-amz-content-sha256;x-amz-date');

$padded = s3_sign_v4(
    'GET', '/x',
    ['host' => '  h  ', 'x-amz-date' => $timestamp, 'x-amz-content-sha256' => $emptyHash],
    $emptyHash, $timestamp, $region, $accessKey, $secret
);
check('header values are trimmed', $padded['signature'], $lower['signature']);

echo "\n== Key encoding ==\n";
check('plain key', s3_encode_path('products/a.webp'), '/products/a.webp');
check('leading slash is not doubled', s3_encode_path('/products/a.webp'), '/products/a.webp');
check('spaces are escaped', s3_encode_path('products/my file.webp'), '/products/my%20file.webp');
check('separators survive escaping', s3_encode_path('a/b/c.webp'), '/a/b/c.webp');
check('plus is escaped, not treated as a space', s3_encode_path('a+b.webp'), '/a%2Bb.webp');

echo "\n== Object URLs ==\n";
$cfg = [
    'enabled' => true, 'key' => 'k', 'secret' => 's', 'bucket' => 'mybucket',
    'endpoint' => 'https://storage.railway.app', 'region' => 'us-east-1',
    'path_style' => true, 'public_base' => '',
];
check('path style', s3_object_url($cfg, 'products/a.webp'), 'https://storage.railway.app/mybucket/products/a.webp');

$cfg['path_style'] = false;
check('virtual-host style', s3_object_url($cfg, 'products/a.webp'), 'https://mybucket.storage.railway.app/products/a.webp');

echo "\n{$pass} passed, {$fail} failed\n";
exit($fail === 0 ? 0 : 1);
