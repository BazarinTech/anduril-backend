<?php
/**
 * COPY EXISTING PRODUCT IMAGES INTO THE BUCKET
 * ============================================
 *     php bin/migrate-images-to-bucket.php            # report only
 *     php bin/migrate-images-to-bucket.php --apply    # actually upload
 *
 * The eight images already in admin/uploads/ are referenced by rows in
 * `products`. Switching STORAGE_DRIVER to s3 without moving them first means
 * every existing product renders a broken image, so this copies them across.
 *
 * Reads from disk, writes to the bucket, and does not delete anything: if the
 * cutover has to be reversed, the originals are still where they were. Files
 * already present in the bucket are skipped, so re-running is safe.
 *
 * CLI ONLY. It uses bucket credentials and iterates the products table; not
 * something to leave reachable over HTTP.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("This is a maintenance script and cannot be run over HTTP.\n");
}

require_once __DIR__ . '/../bootstrap/api.php';

$apply = in_array('--apply', $argv, true);

$cfg = s3_config();

if (!$cfg['enabled']) {
    fwrite(STDERR, "Object storage is not configured.\n");
    fwrite(STDERR, "Set S3_ENDPOINT, S3_ACCESS_KEY_ID, S3_SECRET_ACCESS_KEY and S3_BUCKET first.\n");
    exit(1);
}

echo "Bucket:   {$cfg['bucket']} at {$cfg['endpoint']}\n";
echo "Prefix:   " . (storage_prefix() ?: '(none)') . "\n";
echo "Source:   " . storage_local_dir() . "\n";
echo $apply ? "Mode:     APPLY\n\n" : "Mode:     dry run (pass --apply to upload)\n\n";

// Check the credentials work before walking anything, so a typo reports once
// rather than once per file.
$health = storage_health();

if (!$health['ok']) {
    fwrite(STDERR, "Storage check failed: {$health['detail']}\n");
    exit(1);
}

echo "Storage check: {$health['detail']}\n\n";

/**
 * Every image the database actually references. Walking the directory instead
 * would also copy files no product points at, and miss rows whose image is
 * missing from disk -- which is the case worth reporting.
 */
$rows = $query->select('products');

$seen = [];
$uploaded = $skipped = $missing = $failed = 0;

foreach ($rows as $row) {
    $filename = basename((string) ($row['image'] ?? ''));

    if ($filename === '') {
        continue;
    }

    if (isset($seen[$filename])) {
        continue; // two products sharing one image
    }
    $seen[$filename] = true;

    $path = storage_local_dir() . $filename;
    $key  = storage_prefix() . $filename;

    /**
     * Bucket first, then disk.
     *
     * The other order reports anything already migrated -- including images
     * uploaded straight to the bucket after the cutover -- as "missing from
     * disk", which reads like data loss and is not.
     */
    if (s3_exists($key)) {
        printf("  SKIP     %-45s already in the bucket\n", $filename);
        $skipped++;
        continue;
    }

    if (!is_file($path)) {
        printf("  MISSING  %-45s (product #%s references it; not in the bucket and not on disk)\n", $filename, $row['ID']);
        $missing++;
        continue;
    }

    if (!$apply) {
        printf("  WOULD    %-45s %s\n", $filename, number_format(filesize($path)) . ' bytes');
        $uploaded++;
        continue;
    }

    $bytes = file_get_contents($path);

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $type  = finfo_file($finfo, $path) ?: 'application/octet-stream';
    finfo_close($finfo);

    if (s3_put($key, $bytes, $type)) {
        printf("  UPLOAD   %-45s %s\n", $filename, $type);
        $uploaded++;
    } else {
        printf("  FAILED   %-45s see the error log\n", $filename);
        $failed++;
    }
}

echo "\n";
printf("%d uploaded, %d skipped, %d missing from disk, %d failed\n", $uploaded, $skipped, $missing, $failed);

if ($missing > 0) {
    echo "\nRows referencing a file that is not on disk were already broken before\n";
    echo "this migration; they will stay broken. Re-upload those products' images\n";
    echo "from the admin panel.\n";
}

if (!$apply && $uploaded > 0) {
    echo "\nNothing was written. Re-run with --apply.\n";
}

exit($failed > 0 ? 1 : 0);
