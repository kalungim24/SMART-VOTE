<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/logging_helper.php';
require_role('admin');

header('Content-Type: application/json');

$logging_helper = new LoggingHelper($pdo);

// Get filters from request
$filters = [
    'user_type' => $_GET['user_type'] ?? '',
    'action' => $_GET['action'] ?? '',
    'date_from' => $_GET['date_from'] ?? '',
    'date_to' => $_GET['date_to'] ?? '',
    'limit' => (int)($_GET['limit'] ?? 50),
    'offset' => (int)($_GET['offset'] ?? 0)
];

try {
    $logs = $logging_helper->getSystemLogs($filters);
    echo json_encode(['success' => true, 'logs' => $logs]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
