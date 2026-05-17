<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';
require_super_admin();

$filename = $_GET['file'] ?? '';
$backup_dir = __DIR__ . '/../backups/';
$filepath = $backup_dir . $filename;

// Security check
if (empty($filename) || !file_exists($filepath)) {
    http_response_code(404);
    die('File not found');
}

// Prevent directory traversal
if (strpos($filename, '..') !== false || strpos($filename, '/') !== false || strpos($filename, '\\') !== false) {
    http_response_code(403);
    die('Access denied');
}

// Set headers for file download
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . basename($filename) . '"');
header('Content-Length: ' . filesize($filepath));
header('Cache-Control: no-cache, must-revalidate');
header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');

// Output file
readfile($filepath);
exit;
