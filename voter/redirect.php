<?php
// Handle incorrect URL access
$request_uri = $_SERVER['REQUEST_URI'];
$script_name = $_SERVER['SCRIPT_NAME'];

// If someone accesses voter/voter/ redirect to voter/
if (strpos($request_uri, '/voter/voter/') !== false) {
    $correct_url = str_replace('/voter/voter/', '/voter/', $request_uri);
    header('Location: ' . $correct_url, true, 301);
    exit;
}

// If someone accesses voter/voter redirect to voter/
if (strpos($request_uri, '/voter/voter') !== false && substr($request_uri, -1) !== '/') {
    $correct_url = str_replace('/voter/voter', '/voter', $request_uri);
    header('Location: ' . $correct_url, true, 301);
    exit;
}
?>
