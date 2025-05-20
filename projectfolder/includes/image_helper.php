<?php

function isGdAvailable() {
    return extension_loaded('gd') && function_exists('imagecreatefromjpeg');
}

function compressAndSaveImage($source_path, $destination_path, $quality = 75) {
    // Check if GD library is available
    if (!isGdAvailable()) {
        // If GD is not available, just move the file
        if (move_uploaded_file($source_path, $destination_path)) {
            return true;
        }
        throw new Exception("Image processing is not available (GD library missing) and file move failed");
    }

    // Get image info
    $info = getimagesize($source_path);
    if ($info === false) {
        throw new Exception("Invalid image file");
    }

    // Check for valid image mime type
    $allowed_types = [
        IMAGETYPE_JPEG => 'jpeg',
        IMAGETYPE_PNG => 'png'
    ];
    
    if (!isset($allowed_types[$info[2]])) {
        throw new Exception("Invalid image type. Only JPEG and PNG are allowed.");
    }

    // Create image from file
    switch ($info[2]) {
        case IMAGETYPE_JPEG:
            $image = imagecreatefromjpeg($source_path);
            break;
        case IMAGETYPE_PNG:
            $image = imagecreatefrompng($source_path);
            break;
        default:
            throw new Exception("Unsupported image type");
    }

    if ($image === false) {
        throw new Exception("Failed to process image");
    }

    // Calculate new dimensions (max 1920px width/height)
    $max_dimension = 1920;
    $width = imagesx($image);
    $height = imagesy($image);
    
    if ($width > $max_dimension || $height > $max_dimension) {
        if ($width > $height) {
            $new_width = $max_dimension;
            $new_height = floor($height * ($max_dimension / $width));
        } else {
            $new_height = $max_dimension;
            $new_width = floor($width * ($max_dimension / $height));
        }
        
        $temp = imagecreatetruecolor($new_width, $new_height);
        
        // Preserve transparency for PNG
        if ($info[2] === IMAGETYPE_PNG) {
            imagealphablending($temp, false);
            imagesavealpha($temp, true);
            $transparent = imagecolorallocatealpha($temp, 255, 255, 255, 127);
            imagefilledrectangle($temp, 0, 0, $new_width, $new_height, $transparent);
        }
        
        imagecopyresampled($temp, $image, 0, 0, 0, 0, $new_width, $new_height, $width, $height);
        imagedestroy($image);
        $image = $temp;
    }

    // Create directory if it doesn't exist
    $dir = dirname($destination_path);
    if (!file_exists($dir)) {
        mkdir($dir, 0755, true);
    }

    // Save the image
    switch ($info[2]) {
        case IMAGETYPE_JPEG:
            imagejpeg($image, $destination_path, $quality);
            break;
        case IMAGETYPE_PNG:
            // PNG quality is 0-9, so convert 0-100 to 0-9
            $png_quality = round(($quality / 100) * 9);
            imagepng($image, $destination_path, 9 - $png_quality);
            break;
    }

    imagedestroy($image);
    return true;
}

function validateImage($file) {
    // Check basic PHP file upload errors
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new Exception(getUploadErrorMessage($file['error']));
    }

    // Check file size (5MB max)
    if ($file['size'] > 5 * 1024 * 1024) {
        throw new Exception('File size must be less than 5MB');
    }

    // Check if the file is actually an image
    $info = @getimagesize($file['tmp_name']);
    if ($info === false) {
        throw new Exception('Invalid image file');
    }

    // Verify MIME type
    $allowed_types = ['image/jpeg', 'image/png'];
    if (!in_array($info['mime'], $allowed_types)) {
        throw new Exception('Invalid image type. Only JPEG and PNG are allowed');
    }

    // Additional security checks
    if (!is_uploaded_file($file['tmp_name'])) {
        throw new Exception('File upload attack detected');
    }

    // Check for PHP code in the file
    $content = file_get_contents($file['tmp_name']);
    if (preg_match('/<\?php/i', $content) || preg_match('/<\?=/i', $content)) {
        throw new Exception('Potential malicious file detected');
    }

    return true;
}

function getUploadErrorMessage($error_code) {
    switch ($error_code) {
        case UPLOAD_ERR_INI_SIZE:
            return 'The uploaded file exceeds the upload_max_filesize directive in php.ini';
        case UPLOAD_ERR_FORM_SIZE:
            return 'The uploaded file exceeds the MAX_FILE_SIZE directive in the HTML form';
        case UPLOAD_ERR_PARTIAL:
            return 'The uploaded file was only partially uploaded';
        case UPLOAD_ERR_NO_FILE:
            return 'No file was uploaded';
        case UPLOAD_ERR_NO_TMP_DIR:
            return 'Missing a temporary folder';
        case UPLOAD_ERR_CANT_WRITE:
            return 'Failed to write file to disk';
        case UPLOAD_ERR_EXTENSION:
            return 'A PHP extension stopped the file upload';
        default:
            return 'Unknown upload error';
    }
} 