<?php
/**
 * Enhanced Real-Time Activity Logger for SmartLonda System
 * Tracks all user actions, system events, and security incidents
 */

class ActivityLogger {
    private $pdo;
    private $session_data;
    
    public function __construct($database_connection) {
        $this->pdo = $database_connection;
        $this->session_data = $this->getSessionData();
    }
    
    /**
     * Log any activity in the system
     */
    public function log($activity_type, $activity_category, $action, $options = []) {
        try {
            // Check if table exists first
            if (!$this->tableExists('activity_logs')) {
                // Table doesn't exist, log to error log instead
                error_log("ActivityLogger: Table 'activity_logs' doesn't exist. Activity: $action");
                return false;
            }
            // Get user information
            $user_data = $this->getUserData();
            
            // Prepare activity data
            $activity_data = array_merge([
                'user_id' => $user_data['user_id'],
                'user_type' => $user_data['user_type'],
                'username' => $user_data['username'],
                'activity_type' => $activity_type,
                'activity_category' => $activity_category,
                'action' => $action,
                'description' => $options['description'] ?? null,
                'target_type' => $options['target_type'] ?? null,
                'target_id' => $options['target_id'] ?? null,
                'target_name' => $options['target_name'] ?? null,
                'ip_address' => $this->getClientIP(),
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
                'request_method' => $_SERVER['REQUEST_METHOD'] ?? null,
                'request_uri' => $_SERVER['REQUEST_URI'] ?? null,
                'session_id' => session_id(),
                'status' => $options['status'] ?? 'success',
                'metadata' => $options['metadata'] ?? null,
                'severity' => $options['severity'] ?? 1
            ], $options);
            
            // Convert metadata to JSON if it's an array
            if (is_array($activity_data['metadata'])) {
                $activity_data['metadata'] = json_encode($activity_data['metadata']);
            }
            
            // Insert into database
            $sql = "INSERT INTO activity_logs (
                user_id, user_type, username, activity_type, activity_category, 
                action, description, target_type, target_id, target_name,
                ip_address, user_agent, request_method, request_uri, session_id,
                status, metadata, severity
            ) VALUES (
                :user_id, :user_type, :username, :activity_type, :activity_category,
                :action, :description, :target_type, :target_id, :target_name,
                :ip_address, :user_agent, :request_method, :request_uri, :session_id,
                :status, :metadata, :severity
            )";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                'user_id' => $activity_data['user_id'],
                'user_type' => $activity_data['user_type'],
                'username' => $activity_data['username'],
                'activity_type' => $activity_data['activity_type'],
                'activity_category' => $activity_data['activity_category'],
                'action' => $activity_data['action'],
                'description' => $activity_data['description'],
                'target_type' => $activity_data['target_type'],
                'target_id' => $activity_data['target_id'],
                'target_name' => $activity_data['target_name'],
                'ip_address' => $activity_data['ip_address'],
                'user_agent' => $activity_data['user_agent'],
                'request_method' => $activity_data['request_method'],
                'request_uri' => $activity_data['request_uri'],
                'session_id' => $activity_data['session_id'],
                'status' => $activity_data['status'],
                'metadata' => $activity_data['metadata'],
                'severity' => $activity_data['severity']
            ]);
            
            return true;
            
        } catch (Exception $e) {
            // Log to error log if database logging fails
            error_log("Activity Logger Error: " . $e->getMessage());
            return false;
        }
    }
    
    // Convenience methods for common activities
    
    public function logLogin($username, $success = true) {
        return $this->log('login', 'authentication', $success ? 'Successful login' : 'Failed login attempt', [
            'description' => $success ? "User '$username' logged in successfully" : "Failed login attempt for '$username'",
            'status' => $success ? 'success' : 'failure',
            'severity' => $success ? 2 : 3,
            'metadata' => ['login_time' => date('Y-m-d H:i:s'), 'attempt_result' => $success ? 'success' : 'failed']
        ]);
    }
    
    public function logLogout($username) {
        return $this->log('logout', 'authentication', 'User logout', [
            'description' => "User '$username' logged out",
            'status' => 'success',
            'severity' => 1,
            'metadata' => ['logout_time' => date('Y-m-d H:i:s')]
        ]);
    }
    
    public function logElectionCreated($election_id, $election_title) {
        return $this->log('create_election', 'election', 'Election created', [
            'description' => "New election '$election_title' was created",
            'target_type' => 'election',
            'target_id' => $election_id,
            'target_name' => $election_title,
            'status' => 'success',
            'severity' => 2,
            'metadata' => ['election_id' => $election_id, 'created_at' => date('Y-m-d H:i:s')]
        ]);
    }
    
    public function logElectionActivated($election_id, $election_title) {
        return $this->log('activate_election', 'election', 'Election activated', [
            'description' => "Election '$election_title' has been activated and voting is now open",
            'target_type' => 'election',
            'target_id' => $election_id,
            'target_name' => $election_title,
            'status' => 'success',
            'severity' => 3,
            'metadata' => ['election_id' => $election_id, 'activated_at' => date('Y-m-d H:i:s')]
        ]);
    }
    
    public function logVoteCast($voter_id, $election_id, $candidate_ids) {
        return $this->log('vote_cast', 'voting', 'Vote cast successfully', [
            'description' => "Vote cast by voter ID: $voter_id",
            'target_type' => 'election',
            'target_id' => $election_id,
            'status' => 'success',
            'severity' => 2,
            'metadata' => [
                'voter_id' => $voter_id,
                'election_id' => $election_id,
                'candidate_count' => is_array($candidate_ids) ? count($candidate_ids) : 1,
                'vote_time' => date('Y-m-d H:i:s')
            ]
        ]);
    }
    
    public function logCandidateAdded($candidate_id, $candidate_name, $position) {
        return $this->log('add_candidate', 'candidate', 'Candidate added', [
            'description' => "New candidate '$candidate_name' added for position '$position'",
            'target_type' => 'candidate',
            'target_id' => $candidate_id,
            'target_name' => $candidate_name,
            'status' => 'success',
            'severity' => 2,
            'metadata' => ['candidate_id' => $candidate_id, 'position' => $position]
        ]);
    }
    
    public function logBackupCreated($backup_name, $backup_size) {
        return $this->log('create_backup', 'backup', 'Database backup created', [
            'description' => "Database backup '$backup_name' created successfully",
            'target_type' => 'backup',
            'target_name' => $backup_name,
            'status' => 'success',
            'severity' => 2,
            'metadata' => [
                'backup_name' => $backup_name,
                'backup_size' => $backup_size,
                'created_at' => date('Y-m-d H:i:s')
            ]
        ]);
    }
    
    public function logBackupRestored($backup_name) {
        return $this->log('restore_backup', 'backup', 'Database backup restored', [
            'description' => "Database restored from backup '$backup_name'",
            'target_type' => 'backup',
            'target_name' => $backup_name,
            'status' => 'success',
            'severity' => 4, // Critical operation
            'metadata' => [
                'backup_name' => $backup_name,
                'restored_at' => date('Y-m-d H:i:s')
            ]
        ]);
    }
    
    public function logDataExport($export_type, $election_id, $file_name) {
        return $this->log('data_export', 'export', "Data exported as $export_type", [
            'description' => "Election data exported in $export_type format",
            'target_type' => 'election',
            'target_id' => $election_id,
            'target_name' => $file_name,
            'status' => 'success',
            'severity' => 2,
            'metadata' => [
                'export_type' => $export_type,
                'election_id' => $election_id,
                'file_name' => $file_name,
                'exported_at' => date('Y-m-d H:i:s')
            ]
        ]);
    }
    
    public function logSecurityEvent($event_type, $description, $severity = 3) {
        return $this->log($event_type, 'security', 'Security event detected', [
            'description' => $description,
            'status' => 'warning',
            'severity' => $severity,
            'metadata' => [
                'event_type' => $event_type,
                'detected_at' => date('Y-m-d H:i:s'),
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null
            ]
        ]);
    }
    
    /**
     * Get recent activities with filtering options
     */
    public function getRecentActivities($limit = 50, $filters = []) {
        try {
            // Check if table exists first
            if (!$this->tableExists('activity_logs')) {
                return []; // Return empty array if table doesn't exist
            }
            
            $where_conditions = [];
            $params = [];
            
            if (!empty($filters['category'])) {
                $where_conditions[] = "activity_category = :category";
                $params['category'] = $filters['category'];
            }
            
            if (!empty($filters['user_type'])) {
                $where_conditions[] = "user_type = :user_type";
                $params['user_type'] = $filters['user_type'];
            }
            
            if (!empty($filters['severity'])) {
                $where_conditions[] = "severity = :severity";
                $params['severity'] = $filters['severity'];
            }
            
            if (!empty($filters['date_from'])) {
                $where_conditions[] = "created_at >= :date_from";
                $params['date_from'] = $filters['date_from'];
            }
            
            $where_clause = empty($where_conditions) ? '' : 'WHERE ' . implode(' AND ', $where_conditions);
            
            $sql = "SELECT * FROM activity_logs $where_clause ORDER BY created_at DESC LIMIT :limit";
            $stmt = $this->pdo->prepare($sql);
            
            // Bind limit parameter
            $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
            
            // Bind other parameters
            foreach ($params as $key => $value) {
                $stmt->bindValue(":$key", $value);
            }
            
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            error_log("ActivityLogger::getRecentActivities error: " . $e->getMessage());
            return []; // Return empty array on error
        }
    }
    
    /**
     * Get activity statistics
     */
    public function getActivityStats($timeframe = '24 hours') {
        try {
            // Check if table exists first
            if (!$this->tableExists('activity_logs')) {
                return []; // Return empty array if table doesn't exist
            }
            
            // Convert timeframe to safe SQL - only allow specific values
            $validTimeframes = [
                '1 hour' => '1 HOUR',
                '24 hours' => '1 DAY', 
                '7 days' => '7 DAY',
                '30 days' => '30 DAY'
            ];
            
            $intervalClause = $validTimeframes[$timeframe] ?? '1 DAY';
            
            $sql = "SELECT 
                        activity_category,
                        status,
                        COUNT(*) as count,
                        AVG(severity) as avg_severity
                    FROM activity_logs 
                    WHERE created_at >= DATE_SUB(NOW(), INTERVAL $intervalClause)
                    GROUP BY activity_category, status
                    ORDER BY count DESC";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            error_log("ActivityLogger::getActivityStats error: " . $e->getMessage());
            return []; // Return empty array on error
        }
    }
    
    // Private helper methods
    
    private function tableExists($tableName) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) 
                FROM information_schema.tables 
                WHERE table_schema = DATABASE() 
                AND table_name = ?
            ");
            $stmt->execute([$tableName]);
            return $stmt->fetchColumn() > 0;
        } catch (Exception $e) {
            error_log("ActivityLogger tableExists error: " . $e->getMessage());
            return false;
        }
    }
    
    private function getUserData() {
        return [
            'user_id' => $_SESSION['user_id'] ?? null,
            'user_type' => $this->detectUserType(),
            'username' => $_SESSION['username'] ?? 'anonymous'
        ];
    }
    
    private function detectUserType() {
        if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
            return 'admin';
        } elseif (isset($_SESSION['voter_id'])) {
            return 'voter';
        } else {
            return 'system';
        }
    }
    
    private function getSessionData() {
        return [
            'session_id' => session_id(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
            'ip_address' => $this->getClientIP()
        ];
    }
    
    private function getClientIP() {
        $ip_keys = ['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'];
        foreach ($ip_keys as $key) {
            if (!empty($_SERVER[$key])) {
                $ips = explode(',', $_SERVER[$key]);
                return trim($ips[0]);
            }
        }
        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }
}

// Global function for easy logging throughout the application
function logActivity($activity_type, $activity_category, $action, $options = []) {
    global $pdo;
    static $logger = null;
    
    if ($logger === null && isset($pdo)) {
        $logger = new ActivityLogger($pdo);
    }
    
    return $logger ? $logger->log($activity_type, $activity_category, $action, $options) : false;
}
?>
