<?php
include 'initiate.php';
require_once __DIR__ . '/../includes/product-image.php';

/**
 * REPLACE A PRODUCT IMAGE
 * =======================
 * The Edit modal's image field posts here as multipart/form-data -- the other
 * fields go to update_products.php as JSON, which cannot carry a file.
 *
 *   POST id=<product>, image=<file>
 *   -> {success, message, image, image_url}
 *
 * initiate.php has already required the 'edit' permission.
 */

$reply = function ($status, $success, $message, array $extra = []) use ($fileGetContent) {
    http_response_code($status);
    $fileGetContent->send_content(['success' => $success, 'message' => $message] + $extra);
    exit;
};

$id = isset($_POST['id']) && is_scalar($_POST['id']) ? (int) $_POST['id'] : 0;

if ($id <= 0) {
    $reply(400, false, 'Invalid record id');
}

try {
    $product = $query->select('products', '*', ['ID' => $id])[0] ?? null;

    if ($product === null) {
        $reply(404, false, 'That product no longer exists.');
    }

    $stored = store_uploaded_product_image($_FILES['image'] ?? null);

    if (!$stored['ok']) {
        $reply(422, false, $stored['error']);
    }

    $previous = (string) ($product['image'] ?? '');

    $query->update('products', ['image' => $stored['name']], ['ID' => $id]);

    /**
     * The old file is removed only after the row points at the new one.
     *
     * In the other order, a failed UPDATE would leave the product naming an
     * image that no longer exists. A failed delete only leaves an orphan in
     * the bucket, which costs storage and nothing else -- so it is logged
     * rather than reported as a failure to the admin, whose change did work.
     */
    if ($previous !== '' && $previous !== $stored['name']) {
        if (!delete_product_image($previous)) {
            error_log('[admin/update_product_image] could not delete old image ' . $previous);
        }
    }
} catch (\Throwable $e) {
    error_log('[admin/update_product_image] ' . $e->getMessage());
    $reply(500, false, 'Could not save the image. Please try again.');
}

$reply(200, true, 'Image updated', [
    'image'     => $stored['name'],
    'image_url' => product_image_url($stored['name']),
]);
