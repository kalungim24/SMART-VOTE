<?php
// Database connection using PDO
// Load database configuration
if (file_exists(__DIR__ . '/db_config.php')) {
    require_once __DIR__ . '/db_config.php';
} else {
    // Fallback to default local development settings
    $db_host = 'localhost';
    $db_name = 'smart_vote';
    $db_user = 'root';
    $db_pass = '';
}

$dsn = "mysql:host={$db_host};dbname={$db_name};charset=utf8mb4";
$pdo_options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
];

try {
    $pdo = new PDO($dsn, $db_user, $db_pass, $pdo_options);
} catch (PDOException $e) {
    http_response_code(500);
    echo 'Database connection failed. Please configure includes/db_connect.php. '; 
    exit;
}

// Bootstrap: ensure at least one admin exists (first-run setup)
// Note: Admin password should be set through proper setup process, not hardcoded
try {
    $exists = $pdo->query("SHOW TABLES LIKE 'admins'")->fetchColumn();
    if ($exists) {
        $count = (int)$pdo->query("SELECT COUNT(*) FROM admins")->fetchColumn();
        if ($count === 0) {
            // Generate a secure random password for initial admin
            $tempPassword = bin2hex(random_bytes(16));
            $hashedPassword = password_hash($tempPassword, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO admins (username, password, fullname, created_at) VALUES (?, ?, ?, NOW())");
            $stmt->execute(['admin', $hashedPassword, 'System Administrator']);
            // Log the temporary password (in production, this should be sent via secure channel)
            error_log("Initial admin created. Temporary password: " . $tempPassword . " - CHANGE IMMEDIATELY");
        }
    }
} catch (Throwable $e) {
    // Ignore bootstrap errors silently
}

// Check and create missing tables if needed
try {
    // Check if system_logs table exists
    $logsExists = $pdo->query("SHOW TABLES LIKE 'system_logs'")->fetchColumn();
    if (!$logsExists) {
        // Run the fix script
        $fixScript = file_get_contents(__DIR__ . '/../database/fix_missing_tables.sql');
        if ($fixScript) {
            $pdo->exec($fixScript);
        }
    }
} catch (Throwable $e) {
    // Log error but don't break the application
    error_log("Database setup error: " . $e->getMessage());
}

