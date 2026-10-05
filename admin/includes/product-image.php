<?php
/**
 * PRODUCT IMAGE UPLOADS
 * =====================
 * One pipeline for a product image, shared by the Add form
 * (admin/products.php) and the Edit modal's image field
 * (admin/actions/update_product_image.php).
 *
 * It validates the upload, normalises the picture to 500x500, and hands the
 * bytes to lib/storage.php -- which writes to the S3 bucket when one is
 * configured and to admin/uploads otherwise.
 *
 * The type is read with finfo rather than $_FILES['type'], because the latter
 * is supplied by the browser and a .php named "photo.png" would announce
 * itself as an image.
 *
 * SVG is stored as supplied: GD cannot rasterise it. That is why the Apache
 * config refuses to execute anything in the uploads directory -- an SVG is a
 * document that can carry script, and it is served from the bucket or from a
 * directory with the PHP engine switched off.
 */

require_once __DIR__ . '/../../lib/storage.php';

if (!function_exists('product_image_upload_error')) {
    /**
     * A PHP upload error code as a sentence an admin can act on.
     */
    function product_image_upload_error($code)
    {
        switch ($code) {
            case UPLOAD_ERR_OK:
                return null;
            case UPLOAD_ERR_NO_FILE:
                return 'Please choose an image.';
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                // php-anduril.ini caps uploads; a larger file never arrives.
                return 'That image is too large. Images must be under 1000KB.';
            case UPLOAD_ERR_PARTIAL:
                return 'The upload did not finish. Please try again.';
            case UPLOAD_ERR_NO_TMP_DIR:
            case UPLOAD_ERR_CANT_WRITE:
            case UPLOAD_ERR_EXTENSION:
                return 'The server could not receive the file. Please try again.';
        }

        return 'The image could not be uploaded.';
    }
}

if (!function_exists('store_uploaded_product_image')) {
    /**
     * Validate, resize and store one uploaded product image.
     *
     * @param array|null $file One entry from $_FILES.
     *
     * @return array{ok:bool, name:string|null, error:string|null}
     *         `name` is the stored filename, which is what products.image holds.
     */
    function store_uploaded_product_image($file)
    {
        $fail = function ($message) {
            return ['ok' => false, 'name' => null, 'error' => $message];
        };

        if (!is_array($file) || !isset($file['error'])) {
            return $fail('Please choose an image.');
        }

        $uploadProblem = product_image_upload_error($file['error']);

        if ($uploadProblem !== null) {
            return $fail($uploadProblem);
        }

        // Guards against a path being passed off as an upload.
        if (empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            return $fail('That file was not received as an upload.');
        }

        $finfo     = finfo_open(FILEINFO_MIME_TYPE);
        $imageType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        $allowedTypes = ['image/png', 'image/jpg', 'image/jpeg', 'image/gif', 'image/svg+xml', 'image/webp'];
        $maxSize      = 1000 * 1024;

        if (!in_array($imageType, $allowedTypes, true)) {
            return $fail('Images must be PNG, JPG, GIF, WEBP or SVG.');
        }

        if ((int) $file['size'] > $maxSize) {
            return $fail('That image is too large. Images must be under 1000KB.');
        }

        $base     = pathinfo($file['name'] ?? 'image', PATHINFO_FILENAME);
        $safeBase = preg_replace('/[^A-Za-z0-9_\-]/', '_', $base);
        $stem     = time() . '_' . ($safeBase === '' ? 'image' : $safeBase);

        if ($imageType === 'image/svg+xml') {
            $stored = store_product_image($stem . '.svg', file_get_contents($file['tmp_name']), 'image/svg+xml');

            return $stored['ok']
                ? ['ok' => true, 'name' => $stored['name'], 'error' => null]
                : $fail($stored['error']);
        }

        switch ($imageType) {
            case 'image/png':  $image = @imagecreatefrompng($file['tmp_name']);  break;
            case 'image/gif':  $image = @imagecreatefromgif($file['tmp_name']);  break;
            case 'image/webp':
                if (!function_exists('imagecreatefromwebp')) {
                    return $fail('WEBP is not supported on this server.');
                }
                $image = @imagecreatefromwebp($file['tmp_name']);
                break;
            default:           $image = @imagecreatefromjpeg($file['tmp_name']); break;
        }

        if (!$image) {
            // The type said image, the decoder disagreed: a corrupt file, or
            // something that merely starts with the right magic bytes.
            return $fail('That image could not be read. Please try another file.');
        }

        $newWidth  = 500;
        $newHeight = 500;
        $resized   = imagecreatetruecolor($newWidth, $newHeight);

        if (in_array($imageType, ['image/png', 'image/gif', 'image/webp'], true)) {
            imagealphablending($resized, false);
            imagesavealpha($resized, true);

            $transparent = imagecolorallocatealpha($resized, 0, 0, 0, 127);
            imagefilledrectangle($resized, 0, 0, $newWidth, $newHeight, $transparent);

            if ($imageType === 'image/gif') {
                imagecolortransparent($resized, $transparent);
            }
        }

        imagecopyresampled($resized, $image, 0, 0, 0, 0, $newWidth, $newHeight, imagesx($image), imagesy($image));

        /**
         * GD writes to a path or to stdout. The resized image is captured from
         * the output buffer so it can be handed to storage as bytes -- writing
         * it to disk first, only to read it back and upload it, would mean a
         * temp file on a filesystem that may not survive the request.
         */
        ob_start();

        if ($imageType === 'image/png') {
            $imageName  = $stem . '.png';
            $outputType = 'image/png';
            imagepng($resized);
        } elseif ($imageType === 'image/gif') {
            $imageName  = $stem . '.gif';
            $outputType = 'image/gif';
            imagegif($resized);
        } elseif ($imageType === 'image/webp') {
            if (!function_exists('imagewebp')) {
                ob_end_clean();
                imagedestroy($resized);
                imagedestroy($image);

                return $fail('WEBP output is not supported on this server (GD is missing WEBP support).');
            }
            $imageName  = $stem . '.webp';
            $outputType = 'image/webp';
            imagewebp($resized, null, 90);
        } else {
            $imageName  = $stem . '.jpg';
            $outputType = 'image/jpeg';
            imagejpeg($resized, null, 90);
        }

        $bytes = ob_get_clean();

        imagedestroy($resized);
        imagedestroy($image);

        $stored = store_product_image($imageName, $bytes, $outputType);

        return $stored['ok']
            ? ['ok' => true, 'name' => $stored['name'], 'error' => null]
            : $fail($stored['error']);
    }
}
