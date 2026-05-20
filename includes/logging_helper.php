<?php
/**
 * Logging Helper Functions for SmartLonda System
 */

class LoggingHelper {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    /**
     * Log user activity
     */
    public function logActivity($user_id, $user_type, $action, $description = null) {
        try {
            $ip_address = $this->getClientIP();
            $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
            
            $stmt = $this->pdo->prepare("
                INSERT INTO system_logs (user_id, user_type, action, description, ip_address, user_agent) 
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$user_id, $user_type, $action, $description, $ip_address, $user_agent]);
            
        } catch (Exception $e) {
            // Log error silently to avoid breaking the main application
        }
    }
    
    /**
     * Log admin activity
     */
    public function logAdminActivity($admin_id, $action, $description = null) {
        $this->logActivity($admin_id, 'admin', $action, $description);
    }
    
    /**
     * Log voter activity
     */
    public function logVoterActivity($voter_id, $action, $description = null) {
        $this->logActivity($voter_id, 'voter', $action, $description);
    }
    
    /**
     * Get system logs with filters
     */
    public function getSystemLogs($filters = []) {
        $where_conditions = [];
        $params = [];
        
        if (!empty($filters['user_type'])) {
            $where_conditions[] = "user_type = ?";
            $params[] = $filters['user_type'];
        }
        
        if (!empty($filters['action'])) {
            $where_conditions[] = "action LIKE ?";
            $params[] = '%' . $filters['action'] . '%';
        }
        
        if (!empty($filters['date_from'])) {
            $where_conditions[] = "created_at >= ?";
            $params[] = $filters['date_from'];
        }
        
        if (!empty($filters['date_to'])) {
            $where_conditions[] = "created_at <= ?";
            $params[] = $filters['date_to'];
        }
        
        if (!empty($filters['user_id'])) {
            $where_conditions[] = "user_id = ?";
            $params[] = $filters['user_id'];
        }
        
        $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
        $limit = $filters['limit'] ?? 100;
        $offset = $filters['offset'] ?? 0;
        
        $query = "
            SELECT sl.*, 
                   COALESCE(a.fullname, v.name) as user_name,
                   sl.user_type
            FROM system_logs sl
            LEFT JOIN admins a ON sl.user_id = a.id AND sl.user_type = 'admin'
            LEFT JOIN voters v ON sl.user_id = v.id AND sl.user_type = 'voter'
            {$where_clause}
            ORDER BY sl.created_at DESC
            LIMIT ? OFFSET ?
        ";
        
        $params[] = $limit;
        $params[] = $offset;
        
        $stmt = $this->pdo->prepare($query);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
    
    /**
     * Get log statistics
     */
    public function getLogStats() {
        $stats = [];
        
        // Total logs
        $stmt = $this->pdo->query("SELECT COUNT(*) as total FROM system_logs");
        $stats['total'] = $stmt->fetchColumn();
        
        // Logs by user type
        $stmt = $this->pdo->query("
            SELECT user_type, COUNT(*) as count 
            FROM system_logs 
            GROUP BY user_type
        ");
        $stats['by_type'] = $stmt->fetchAll();
        
        // Recent activity (last 24 hours)
        $stmt = $this->pdo->query("
            SELECT COUNT(*) as recent 
            FROM system_logs 
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
        ");
        $stats['recent'] = $stmt->fetchColumn();
        
        // Most common actions
        $stmt = $this->pdo->query("
            SELECT action, COUNT(*) as count 
            FROM system_logs 
            GROUP BY action 
            ORDER BY count DESC 
            LIMIT 10
        ");
        $stats['top_actions'] = $stmt->fetchAll();
        
        return $stats;
    }
    
    /**
     * Clean old logs (older than specified days)
     */
    public function cleanOldLogs($days = 90) {
        try {
            $stmt = $this->pdo->prepare("
                DELETE FROM system_logs 
                WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)
            ");
            $stmt->execute([$days]);
            return $stmt->rowCount();
        } catch (Exception $e) {
            return 0;
        }
    }
    
    /**
     * Export logs to CSV
     */
    public function exportLogsToCSV($filters = []) {
        $logs = $this->getSystemLogs($filters);
        
        $filename = 'system_logs_' . date('Y-m-d_H-i-s') . '.csv';
        $filepath = __DIR__ . '/../exports/' . $filename;
        
        // Create exports directory if it doesn't exist
        $export_dir = dirname($filepath);
        if (!file_exists($export_dir)) {
            mkdir($export_dir, 0755, true);
        }
        
        $file = fopen($filepath, 'w');
        
        // CSV headers
        fputcsv($file, ['ID', 'User Type', 'User Name', 'Action', 'Description', 'IP Address', 'Created At']);
        
        // CSV data
        foreach ($logs as $log) {
            fputcsv($file, [
                $log['id'],
                $log['user_type'],
                $log['user_name'] ?? 'Unknown',
                $log['action'],
                $log['description'],
                $log['ip_address'],
                $log['created_at']
            ]);
        }
        
        fclose($file);
        
        return [
            'filename' => $filename,
            'filepath' => $filepath,
            'size' => filesize($filepath)
        ];
    }
    
    /**
     * Get client IP address
     */
    private function getClientIP() {
        $ip_keys = ['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'];
        foreach ($ip_keys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                foreach (explode(',', $_SERVER[$key]) as $ip) {
                    $ip = trim($ip);
                    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                        return $ip;
                    }
                }
            }
        }
        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }
}
