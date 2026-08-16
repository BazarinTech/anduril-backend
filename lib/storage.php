<?php
/**
 * PRODUCT IMAGE STORAGE
 * =====================
 * One place that knows where product images live and how to build a URL for
 * one. Everything else -- the admin upload form, the app's product list --
 * goes through here.
 *
 * WHY THIS EXISTS
 * ---------------
 * Images were written to `admin/uploads/` on the web host, and the app built
 * URLs by pasting the filename onto a hardcoded domain:
 *
 *     `https://sanderson.xgramm.com/admin/uploads/${image}`
 *
 * That works exactly as long as the code runs on one machine that keeps its
 * disk. On a container platform it does not: the filesystem is ephemeral, so
 * every deploy silently discards every image uploaded since the last one, and
 * a second instance serves 404s for anything the first one received.
 *
 * Objects now go to S3-compatible storage (Railway's Bucket) and the database
 * keeps the same bare filename it always did. Only the resolver changed.
 *
 * DRIVERS
 * -------
 *   local  Disk, as before. Still the right answer for development.
 *   s3     Object storage. Set STORAGE_DRIVER=s3 plus the S3_* keys.
 *
 * The driver defaults to 's3' when a bucket is fully configured and 'local'
 * otherwise, so a developer with no bucket keeps working and a deploy with
 * one does the right thing without a second setting to forget.
 *
 * SERVING
 * -------
 * Three ways to turn a key into something an <img> can load, chosen with
 * STORAGE_URL_MODE:
 *
 *   public    Straight at the bucket's public base URL. Fastest, cacheable,
 *             needs the bucket (or that prefix) to be publicly readable.
 *   presign   A signed, expiring URL minted per request. Keeps the bucket
 *             private; URLs cannot be shared indefinitely or hotlinked.
 *   proxy     Through backend/mains/image.php on this server. Keeps the
 *             bucket private and the URLs stable, at the cost of a PHP
 *             request per image.
 *
 * Product images are catalogue art shown to every visitor -- there is nothing
 * confidential in them -- so 'public' is the default when a public base URL is
 * configured. It falls back to 'presign' when one is not, which is the safe
 * direction to fail in: a private bucket with no public URL would otherwise
 * render every image as a broken link.
 */

require_once __DIR__ . '/s3.php';

if (!function_exists('storage_driver')) {
    function storage_driver()
    {
        $configured = strtolower((string) env('STORAGE_DRIVER', ''));

        if ($configured === 'local' || $configured === 's3') {
            return $configured;
        }

        return s3_config()['enabled'] ? 's3' : 'local';
    }
}

if (!function_exists('storage_prefix')) {
    /** Key prefix inside the bucket. Keeps room for other kinds of upload. */
    function storage_prefix()
    {
        $prefix = trim((string) env('S3_PREFIX', 'products'), '/');

        return $prefix === '' ? '' : $prefix . '/';
    }
}

if (!function_exists('storage_local_dir')) {
    function storage_local_dir()
    {
        return rtrim((string) env('UPLOAD_DIR', __DIR__ . '/../admin/uploads'), '/') . '/';
    }
}

if (!function_exists('store_product_image')) {
    /**
     * Persist an already-processed image.
     *
     * Takes the bytes rather than a path because the upload handler resizes
     * through GD and holds the result in memory; writing it to disk first
     * only to read it back would be a temp file for no reason.
     *
     * @return array{ok:bool, name:string, error:string|null}
     */
    function store_product_image($filename, $bytes, $contentType)
    {
        $filename = basename($filename);

        if (storage_driver() === 's3') {
            $key = storage_prefix() . $filename;

            if (!s3_put($key, $bytes, $contentType)) {
                return [
                    'ok'    => false,
                    'name'  => '',
                    // The specific reason is in the error log; the admin gets
                    // something they can act on.
                    'error' => 'Could not upload the image to storage. Check the bucket settings and try again.',
                ];
            }

            return ['ok' => true, 'name' => $filename, 'error' => null];
        }

        $dir = storage_local_dir();

        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            return ['ok' => false, 'name' => '', 'error' => 'Could not create the upload directory.'];
        }

        if (file_put_contents($dir . $filename, $bytes) === false) {
            return ['ok' => false, 'name' => '', 'error' => 'Could not write the image to disk.'];
        }

        return ['ok' => true, 'name' => $filename, 'error' => null];
    }
}

if (!function_exists('delete_product_image')) {
    function delete_product_image($filename)
    {
        $filename = basename((string) $filename);

        if ($filename === '') {
            return true;
        }

        if (storage_driver() === 's3') {
            return s3_delete(storage_prefix() . $filename);
        }

        $path = storage_local_dir() . $filename;

        return !is_file($path) || unlink($path);
    }
}

if (!function_exists('product_image_url')) {
    /**
     * Turn the stored filename into something the app can load.
     *
     * Returns '' for a product with no image, so the caller can fall through
     * to its own placeholder rather than rendering a broken one.
     */
    function product_image_url($filename)
    {
        $filename = basename((string) $filename);

        if ($filename === '') {
            return '';
        }

        if (storage_driver() !== 's3') {
            // Same shape the app has always received, so nothing downstream
            // has to special-case development.
            $base = rtrim((string) env('LOCAL_UPLOAD_BASE_URL', (string) env('APP_URL', '') . '/admin/uploads'), '/');

            return $base . '/' . rawurlencode($filename);
        }

        $cfg  = s3_config();
        $key  = storage_prefix() . $filename;
        $mode = strtolower((string) env('STORAGE_URL_MODE', ''));

        if ($mode === '') {
            $mode = $cfg['public_base'] !== '' ? 'public' : 'presign';
        }

        if ($mode === 'proxy') {
            return rtrim((string) env('APP_URL', ''), '/')
                . '/backend/mains/image.php?f=' . rawurlencode($filename);
        }

        if ($mode === 'presign') {
            return s3_presign($key, (int) env('S3_PRESIGN_TTL', 86400));
        }

        // 'public'. If no explicit public base is set, the object URL is the
        // best guess -- correct for buckets whose endpoint serves reads.
        $base = $cfg['public_base'];

        return $base !== ''
            ? $base . s3_encode_path($key)
            : s3_object_url($cfg, $key);
    }
}

if (!function_exists('storage_health')) {
    /**
     * Round-trip a small object so the admin can tell a misconfigured bucket
     * from a working one without uploading a real product image and wondering
     * why it did not appear.
     *
     * @return array{ok:bool, driver:string, detail:string}
     */
    function storage_health()
    {
        $driver = storage_driver();

        if ($driver !== 's3') {
            $dir = storage_local_dir();

            return [
                'ok'     => is_dir($dir) && is_writable($dir),
                'driver' => 'local',
                'detail' => is_dir($dir)
                    ? (is_writable($dir) ? 'Writing to ' . $dir : $dir . ' is not writable')
                    : $dir . ' does not exist',
            ];
        }

        $cfg = s3_config();

        if (!$cfg['enabled']) {
            return [
                'ok'     => false,
                'driver' => 's3',
                'detail' => 'Missing one of S3_ENDPOINT, S3_ACCESS_KEY_ID, S3_SECRET_ACCESS_KEY, S3_BUCKET',
            ];
        }

        $key   = storage_prefix() . '.healthcheck-' . bin2hex(random_bytes(4));
        $put   = s3_request('PUT', $key, 'ok', ['Content-Type' => 'text/plain']);

        if (!$put['ok']) {
            return ['ok' => false, 'driver' => 's3', 'detail' => 'Write failed: ' . $put['error']];
        }

        $get = s3_request('GET', $key);
        s3_delete($key);

        if (!$get['ok']) {
            return ['ok' => false, 'driver' => 's3', 'detail' => 'Wrote but could not read back: ' . $get['error']];
        }

        if (trim($get['body']) !== 'ok') {
            return ['ok' => false, 'driver' => 's3', 'detail' => 'Read back unexpected content'];
        }

        return [
            'ok'     => true,
            'driver' => 's3',
            'detail' => 'Read and wrote ' . $cfg['bucket'] . ' at ' . $cfg['endpoint'],
        ];
    }
}
