<?php 

namespace Bazarin\Helpers;

class FileHelper {
    // Upload a file with optional type and size validation
    public static function upload($file, $destination, $allowedTypes = [], $maxSize = 50) {
        // Check if the file was uploaded
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new \Exception('File upload failed with error code: ' . $file['error']);
        }

        // Optional: Check file type
        if (!empty($allowedTypes)) {
            $fileType = mime_content_type($file['tmp_name']);
            if (!in_array($fileType, $allowedTypes)) {
                throw new \Exception('Invalid file type. Allowed types are: ' . implode(', ', $allowedTypes));
            }
        }

        // Optional: Check file size
        if ($maxSize > 0 && $file['size'] > $maxSize) {
            throw new \Exception('File is too large. Maximum size is ' . $maxSize . ' bytes.');
        }

        // Move the uploaded file to the destination
        if (move_uploaded_file($file['tmp_name'], $destination)) {
            return $destination;
        }
        
        // If the file couldn't be moved, throw an exception
        throw new \Exception('File upload failed.');
    }
}
