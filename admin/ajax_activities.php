<?php
/**
 * AJAX endpoint for real-time activity updates
 */

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/activity_logger.php';
require_super_admin();

// Set JSON response header
header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

$action = $_GET['action'] ?? '';
$activityLogger = new ActivityLogger($pdo);

try {
    switch ($action) {
        case 'get_recent_activities':
            $limit = (int)($_GET['limit'] ?? 50);
            $category = $_GET['category'] ?? null;
            
            $filters = [];
            if ($category && $category !== 'all') {
                $filters['category'] = $category;
            }
            
            $activities = $activityLogger->getRecentActivities($limit, $filters);
            
            echo json_encode([
                'success' => true,
                'data' => $activities,
                'count' => count($activities),
                'timestamp' => date('Y-m-d H:i:s')
            ]);
            break;
            
        case 'get_activity_stats':
            $timeframe = $_GET['timeframe'] ?? '24 hours';
            $stats = $activityLogger->getActivityStats($timeframe);
            
            echo json_encode([
                'success' => true,
                'data' => $stats,
                'timeframe' => $timeframe,
                'timestamp' => date('Y-m-d H:i:s')
            ]);
            break;
            
        case 'get_live_count':
            // Get count of activities in the last 5 minutes
            $recentCount = $pdo->query("
                SELECT COUNT(*) FROM activity_logs 
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)
            ")->fetchColumn();
            
            echo json_encode([
                'success' => true,
                'recent_count' => (int)$recentCount,
                'timestamp' => date('Y-m-d H:i:s')
            ]);
            break;
            
        default:
            throw new Exception('Invalid action');
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}
?>
