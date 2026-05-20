<?php
/**
 * SmartLonda System - Main Entry Point
 * Redirects users to appropriate login page
 */

// Use the session handler from includes
require_once __DIR__ . '/includes/session.php';

// Check if user is already logged in
if (isset($_SESSION['user_role'])) {
    if ($_SESSION['user_role'] === 'admin' || $_SESSION['user_role'] === 'super_admin') {
        header('Location: admin/dashboard.php');
    } elseif ($_SESSION['user_role'] === 'voter') {
        header('Location: voter/dashboard.php');
    }
    exit;
}

header('Location: login.php');
exit;
