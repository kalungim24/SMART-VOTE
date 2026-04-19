<?php
/**
 * File Upload Helper Functions
 * Handles secure file uploads for candidates
 */

function upload_candidate_photo($file, $candidateId) {
    $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
    $maxSize = 5 * 1024 * 1024; // 5MB
    $uploadDir = __DIR__ . '/../uploads/candidates/photos/';
    
    return upload_file($file, $uploadDir, $allowedTypes, $maxSize, $candidateId, 'photo');
}

function upload_candidate_symbol($file, $candidateId) {
    $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml'];
    $maxSize = 2 * 1024 * 1024; // 2MB
    $uploadDir = __DIR__ . '/../uploads/candidates/symbols/';
    
    return upload_file($file, $uploadDir, $allowedTypes, $maxSize, $candidateId, 'symbol');
}

function upload_file($file, $uploadDir, $allowedTypes, $maxSize, $candidateId, $type) {
    // Check if file was uploaded
    if (!isset($file['tmp_name']) || empty($file['tmp_name'])) {
        return ['success' => false, 'error' => 'No file uploaded'];
    }
    
    // Check for upload errors
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'error' => 'Upload error: ' . $file['error']];
    }
    
    // Check file size
    if ($file['size'] > $maxSize) {
        $maxSizeMB = round($maxSize / (1024 * 1024), 1);
        return ['success' => false, 'error' => "File too large. Maximum size: {$maxSizeMB}MB"];
    }
    
    // Check file type
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    if (!in_array($mimeType, $allowedTypes)) {
        return ['success' => false, 'error' => 'Invalid file type. Allowed: ' . implode(', ', $allowedTypes)];
    }
    
    // Generate unique filename
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = "candidate_{$candidateId}_{$type}_" . time() . "_" . uniqid() . ".{$extension}";
    $filepath = $uploadDir . $filename;
    
    // Create directory if it doesn't exist
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    // Move uploaded file
    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        return [
            'success' => true, 
            'filename' => $filename,
            'filepath' => $filepath,
            'url' => 'uploads/candidates/' . ($type === 'photo' ? 'photos' : 'symbols') . '/' . $filename
        ];
    } else {
        return ['success' => false, 'error' => 'Failed to save file'];
    }
}

function delete_candidate_file($filepath) {
    if (file_exists($filepath)) {
        return unlink($filepath);
    }
    return true; // File doesn't exist, consider it deleted
}

function get_candidate_file_url($filename, $type) {
    if (empty($filename)) {
        return null;
    }
    
    $subdir = $type === 'photo' ? 'photos' : 'symbols';
    return "uploads/candidates/{$subdir}/{$filename}";
}

function validate_image_file($file) {
    $errors = [];
    
    if (!isset($file['tmp_name']) || empty($file['tmp_name'])) {
        return $errors; // No file uploaded, that's okay
    }
    
    // Check if it's actually an image
    $imageInfo = getimagesize($file['tmp_name']);
    if ($imageInfo === false) {
        $errors[] = 'File is not a valid image';
    }
    
    return $errors;
}
?>
