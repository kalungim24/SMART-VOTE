<?php
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/db_connect.php';
require_once __DIR__ . '/includes/logging_helper.php';

$logging_helper = new LoggingHelper($pdo);

// Get user role and ID before clearing session
$userRole = $_SESSION['user_role'] ?? null;
$userId = $_SESSION['admin_id'] ?? $_SESSION['voter_id'] ?? null;

// Determine appropriate redirect URL based on user role
$redirectUrl = 'index.php'; // Default to main page
if ($userRole === 'voter') {
    $redirectUrl = 'voter/index.php';
} elseif ($userRole === 'admin') {
    $redirectUrl = 'admin/index.php';
}

// Log logout activity
if ($userRole && $userId) {
    if ($userRole === 'admin') {
        $logging_helper->logAdminActivity($userId, 'admin_logout', "Admin logged out");
    } else {
        $logging_helper->logVoterActivity($userId, 'voter_logout', "Voter logged out");
    }
}

// Clear session and redirect to appropriate login
$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
}
session_destroy();

// Redirect to appropriate login page based on user role with logout confirmation
header("Location: $redirectUrl?logout=success");
exit;

