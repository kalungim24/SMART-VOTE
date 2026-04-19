<?php
/**
 * SmartVote Enhanced Audit Logging System
 * Comprehensive audit trail with detailed tracking and compliance features
 */

class AuditLogger {
    private $pdo;
    private $security;
    private $sessionId;
    
    public function __construct($pdo, $security) {
        $this->pdo = $pdo;
        $this->security = $security;
        $this->sessionId = session_id();
        $this->initAuditTables();
    }
    
    /**
     * Initialize comprehensive audit tables
     */
    private function initAuditTables() {
        try {
            // Main audit log table
            $this->pdo->exec("
                CREATE TABLE IF NOT EXISTS audit_log (
                    id BIGINT AUTO_INCREMENT PRIMARY KEY,
                    session_id VARCHAR(128) NOT NULL,
                    user_id INT NULL,
                    user_type ENUM('admin', 'voter', 'system', 'anonymous') NOT NULL,
                    username VARCHAR(255) NULL,
                    action_category ENUM('authentication', 'authorization', 'data_access', 'data_modification', 'system_configuration', 'security_event') NOT NULL,
                    action_type VARCHAR(100) NOT NULL,
                    resource_type VARCHAR(100) NOT NULL,
                    resource_id VARCHAR(100) NULL,
                    description TEXT NOT NULL,
                    result ENUM('success', 'failure', 'partial', 'blocked') NOT NULL,
                    severity ENUM('low', 'medium', 'high', 'critical') DEFAULT 'low',
                    ip_address VARCHAR(45) NOT NULL,
                    user_agent TEXT,
                    request_method VARCHAR(10),
                    request_uri VARCHAR(500),
                    request_params JSON NULL,
                    response_status INT NULL,
                    before_data JSON NULL,
                    after_data JSON NULL,
                    metadata JSON NULL,
                    risk_score INT DEFAULT 0,
                    compliance_flags JSON NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    
                    INDEX idx_user_time (user_id, user_type, created_at),
                    INDEX idx_action_time (action_type, created_at),
                    INDEX idx_category_severity (action_category, severity),
                    INDEX idx_session (session_id),
                    INDEX idx_ip (ip_address),
                    INDEX idx_resource (resource_type, resource_id),
                    INDEX idx_risk_score (risk_score, created_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
            
            // User session tracking
            $this->pdo->exec("
                CREATE TABLE IF NOT EXISTS user_sessions (
                    id BIGINT AUTO_INCREMENT PRIMARY KEY,
                    session_id VARCHAR(128) NOT NULL UNIQUE,
                    user_id INT NULL,
                    user_type ENUM('admin', 'voter', 'anonymous') NOT NULL,
                    username VARCHAR(255) NULL,
                    ip_address VARCHAR(45) NOT NULL,
                    user_agent TEXT,
                    country_code VARCHAR(2),
                    country_name VARCHAR(100),
                    login_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    logout_time TIMESTAMP NULL,
                    logout_type ENUM('manual', 'timeout', 'forced', 'system') NULL,
                    session_duration INT NULL, -- in seconds
                    actions_count INT DEFAULT 0,
                    risk_events_count INT DEFAULT 0,
                    is_active BOOLEAN DEFAULT TRUE,
                    
                    INDEX idx_user_active (user_id, user_type, is_active),
                    INDEX idx_session_active (session_id, is_active),
                    INDEX idx_login_time (login_time),
                    INDEX idx_ip (ip_address)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
            
            // Data change tracking
            $this->pdo->exec("
                CREATE TABLE IF NOT EXISTS data_changes (
                    id BIGINT AUTO_INCREMENT PRIMARY KEY,
                    audit_log_id BIGINT NOT NULL,
                    table_name VARCHAR(100) NOT NULL,
                    record_id VARCHAR(100) NOT NULL,
                    field_name VARCHAR(100) NOT NULL,
                    old_value TEXT NULL,
                    new_value TEXT NULL,
                    value_type ENUM('string', 'number', 'boolean', 'json', 'encrypted') DEFAULT 'string',
                    change_type ENUM('insert', 'update', 'delete') NOT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    
                    FOREIGN KEY (audit_log_id) REFERENCES audit_log(id) ON DELETE CASCADE,
                    INDEX idx_table_record (table_name, record_id),
                    INDEX idx_audit_log (audit_log_id),
                    INDEX idx_field_change (field_name, change_type)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
            
            // Compliance audit trail
            $this->pdo->exec("
                CREATE TABLE IF NOT EXISTS compliance_events (
                    id BIGINT AUTO_INCREMENT PRIMARY KEY,
                    audit_log_id BIGINT NOT NULL,
                    compliance_type ENUM('gdpr', 'election_law', 'data_retention', 'access_control', 'audit_trail') NOT NULL,
                    requirement_met BOOLEAN NOT NULL,
                    details TEXT,
                    evidence JSON NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    
                    FOREIGN KEY (audit_log_id) REFERENCES audit_log(id) ON DELETE CASCADE,
                    INDEX idx_compliance_type (compliance_type, requirement_met),
                    INDEX idx_audit_log (audit_log_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
            
        } catch (Exception $e) {
            error_log("AuditLogger: Failed to initialize tables - " . $e->getMessage());
        }
    }
    
    /**
     * Log comprehensive audit event
     */
    public function log($actionCategory, $actionType, $resourceType, $description, $options = []) {
        try {
            $userId = $_SESSION['user_id'] ?? $_SESSION['voter_pk'] ?? null;
            $userType = $_SESSION['user_role'] ?? 'anonymous';
            $username = $_SESSION['username'] ?? $_SESSION['voter_id'] ?? null;
            
            // Calculate risk score
            $riskScore = $this->calculateRiskScore($actionCategory, $actionType, $options);
            
            // Prepare audit data
            $auditData = [
                'session_id' => $this->sessionId,
                'user_id' => $userId,
                'user_type' => $userType,
                'username' => $username,
                'action_category' => $actionCategory,
                'action_type' => $actionType,
                'resource_type' => $resourceType,
                'resource_id' => $options['resource_id'] ?? null,
                'description' => $description,
                'result' => $options['result'] ?? 'success',
                'severity' => $options['severity'] ?? 'low',
                'ip_address' => $this->security->getClientIP(),
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
                'request_method' => $_SERVER['REQUEST_METHOD'] ?? null,
                'request_uri' => $_SERVER['REQUEST_URI'] ?? null,
                'request_params' => isset($options['request_params']) ? json_encode($options['request_params']) : null,
                'response_status' => $options['response_status'] ?? null,
                'before_data' => isset($options['before_data']) ? json_encode($options['before_data']) : null,
                'after_data' => isset($options['after_data']) ? json_encode($options['after_data']) : null,
                'metadata' => isset($options['metadata']) ? json_encode($options['metadata']) : null,
                'risk_score' => $riskScore,
                'compliance_flags' => isset($options['compliance_flags']) ? json_encode($options['compliance_flags']) : null
            ];
            
            // Insert audit record
            $sql = "INSERT INTO audit_log (" . implode(', ', array_keys($auditData)) . ") VALUES (" . str_repeat('?,', count($auditData) - 1) . "?)";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(array_values($auditData));
            
            $auditLogId = $this->pdo->lastInsertId();
            
            // Log data changes if provided
            if (!empty($options['data_changes'])) {
                $this->logDataChanges($auditLogId, $options['data_changes']);
            }
            
            // Log compliance events if provided
            if (!empty($options['compliance_events'])) {
                $this->logComplianceEvents($auditLogId, $options['compliance_events']);
            }
            
            // Update session activity
            $this->updateSessionActivity();
            
            // Check for security patterns
            $this->checkSecurityPatterns($actionType, $riskScore);
            
            return $auditLogId;
            
        } catch (Exception $e) {
            error_log("AuditLogger: log error - " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Start user session tracking
     */
    public function startSession($userId, $userType, $username) {
        try {
            // Get geolocation data
            $geoData = $this->getGeolocationData();
            
            $stmt = $this->pdo->prepare("
                INSERT INTO user_sessions (session_id, user_id, user_type, username, ip_address, user_agent, country_code, country_name)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                user_id = VALUES(user_id),
                user_type = VALUES(user_type),
                username = VALUES(username),
                last_activity = CURRENT_TIMESTAMP,
                is_active = TRUE
            ");
            
            $stmt->execute([
                $this->sessionId,
                $userId,
                $userType,
                $username,
                $this->security->getClientIP(),
                $_SERVER['HTTP_USER_AGENT'] ?? null,
                $geoData['country_code'] ?? null,
                $geoData['country_name'] ?? null
            ]);
            
            // Log session start
            $this->log('authentication', 'session_start', 'user_session', "Session started for $userType: $username", [
                'metadata' => [
                    'session_id' => $this->sessionId,
                    'geolocation' => $geoData
                ]
            ]);
            
        } catch (Exception $e) {
            error_log("AuditLogger: startSession error - " . $e->getMessage());
        }
    }
    
    /**
     * End user session tracking
     */
    public function endSession($logoutType = 'manual') {
        try {
            $stmt = $this->pdo->prepare("
                UPDATE user_sessions 
                SET logout_time = CURRENT_TIMESTAMP,
                    logout_type = ?,
                    session_duration = TIMESTAMPDIFF(SECOND, login_time, CURRENT_TIMESTAMP),
                    is_active = FALSE
                WHERE session_id = ? AND is_active = TRUE
            ");
            $stmt->execute([$logoutType, $this->sessionId]);
            
            // Get session data for logging
            $stmt = $this->pdo->prepare("
                SELECT user_type, username, session_duration, actions_count 
                FROM user_sessions 
                WHERE session_id = ?
            ");
            $stmt->execute([$this->sessionId]);
            $sessionData = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($sessionData) {
                $this->log('authentication', 'session_end', 'user_session', "Session ended ({$logoutType})", [
                    'metadata' => [
                        'session_id' => $this->sessionId,
                        'duration_seconds' => $sessionData['session_duration'],
                        'actions_performed' => $sessionData['actions_count'],
                        'logout_type' => $logoutType
                    ]
                ]);
            }
            
        } catch (Exception $e) {
            error_log("AuditLogger: endSession error - " . $e->getMessage());
        }
    }
    
    /**
     * Log data changes with detailed field tracking
     */
    private function logDataChanges($auditLogId, $changes) {
        try {
            foreach ($changes as $change) {
                $stmt = $this->pdo->prepare("
                    INSERT INTO data_changes (audit_log_id, table_name, record_id, field_name, old_value, new_value, value_type, change_type)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ");
                
                $stmt->execute([
                    $auditLogId,
                    $change['table'],
                    $change['record_id'],
                    $change['field'],
                    $change['old_value'] ?? null,
                    $change['new_value'] ?? null,
                    $change['value_type'] ?? 'string',
                    $change['change_type']
                ]);
            }
        } catch (Exception $e) {
            error_log("AuditLogger: logDataChanges error - " . $e->getMessage());
        }
    }
    
    /**
     * Log compliance events
     */
    private function logComplianceEvents($auditLogId, $events) {
        try {
            foreach ($events as $event) {
                $stmt = $this->pdo->prepare("
                    INSERT INTO compliance_events (audit_log_id, compliance_type, requirement_met, details, evidence)
                    VALUES (?, ?, ?, ?, ?)
                ");
                
                $stmt->execute([
                    $auditLogId,
                    $event['type'],
                    $event['requirement_met'],
                    $event['details'] ?? null,
                    isset($event['evidence']) ? json_encode($event['evidence']) : null
                ]);
            }
        } catch (Exception $e) {
            error_log("AuditLogger: logComplianceEvents error - " . $e->getMessage());
        }
    }
    
    /**
     * Calculate risk score for action
     */
    private function calculateRiskScore($category, $action, $options) {
        $baseScore = 0;
        
        // Category-based scoring
        $categoryScores = [
            'authentication' => 20,
            'authorization' => 30,
            'data_access' => 40,
            'data_modification' => 60,
            'system_configuration' => 80,
            'security_event' => 100
        ];
        
        $baseScore += $categoryScores[$category] ?? 10;
        
        // Action-specific modifiers
        $actionModifiers = [
            'login_success' => 0,
            'login_failure' => 20,
            'password_change' => 30,
            'admin_action' => 40,
            'data_export' => 50,
            'system_config_change' => 70,
            'security_violation' => 100
        ];
        
        $baseScore += $actionModifiers[$action] ?? 0;
        
        // Result-based modifiers
        if (isset($options['result'])) {
            switch ($options['result']) {
                case 'failure':
                    $baseScore += 30;
                    break;
                case 'blocked':
                    $baseScore += 50;
                    break;
                case 'partial':
                    $baseScore += 20;
                    break;
            }
        }
        
        // Time-based modifiers (outside business hours = higher risk)
        $hour = (int)date('H');
        if ($hour < 6 || $hour > 22) {
            $baseScore += 20;
        }
        
        return min(100, max(0, $baseScore));
    }
    
    /**
     * Update session activity counter
     */
    private function updateSessionActivity() {
        try {
            $stmt = $this->pdo->prepare("
                UPDATE user_sessions 
                SET actions_count = actions_count + 1,
                    last_activity = CURRENT_TIMESTAMP
                WHERE session_id = ? AND is_active = TRUE
            ");
            $stmt->execute([$this->sessionId]);
        } catch (Exception $e) {
            error_log("AuditLogger: updateSessionActivity error - " . $e->getMessage());
        }
    }
    
    /**
     * Check for suspicious security patterns
     */
    private function checkSecurityPatterns($actionType, $riskScore) {
        try {
            // Check for multiple high-risk actions in short time
            if ($riskScore >= 70) {
                $stmt = $this->pdo->prepare("
                    UPDATE user_sessions 
                    SET risk_events_count = risk_events_count + 1
                    WHERE session_id = ? AND is_active = TRUE
                ");
                $stmt->execute([$this->sessionId]);
                
                // Check if risk threshold exceeded
                $stmt = $this->pdo->prepare("
                    SELECT risk_events_count FROM user_sessions 
                    WHERE session_id = ? AND is_active = TRUE
                ");
                $stmt->execute([$this->sessionId]);
                $riskCount = $stmt->fetchColumn();
                
                if ($riskCount >= 5) {
                    $this->security->logSecurityEvent('high_risk_session', 'high', [
                        'session_id' => $this->sessionId,
                        'risk_events' => $riskCount
                    ], 'Session flagged for multiple high-risk actions');
                }
            }
        } catch (Exception $e) {
            error_log("AuditLogger: checkSecurityPatterns error - " . $e->getMessage());
        }
    }
    
    /**
     * Get geolocation data (mock implementation)
     */
    private function getGeolocationData() {
        // Mock implementation - in production, use IP geolocation service
        return [
            'country_code' => 'US',
            'country_name' => 'United States'
        ];
    }
    
    /**
     * Get audit statistics
     */
    public function getAuditStatistics($timeframe = '24 hours') {
        try {
            $intervalMap = [
                '1 hour' => '1 HOUR',
                '24 hours' => '1 DAY',
                '7 days' => '7 DAY',
                '30 days' => '30 DAY'
            ];
            
            $interval = $intervalMap[$timeframe] ?? '1 DAY';
            
            // Use prepared statement with parameter binding for interval
            $stats = [];
            
            // Action category breakdown - use prepared statement
            $stmt = $this->pdo->prepare("
                SELECT 
                    action_category,
                    result,
                    COUNT(*) as count,
                    AVG(risk_score) as avg_risk
                FROM audit_log 
                WHERE created_at > DATE_SUB(NOW(), INTERVAL ?)
                GROUP BY action_category, result
                ORDER BY action_category, result
            ");
            $stmt->execute([$interval]);
            $stats['category_breakdown'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // High-risk actions - use prepared statement
            $stmt = $this->pdo->prepare("
                SELECT 
                    action_type,
                    COUNT(*) as count,
                    MAX(risk_score) as max_risk,
                    AVG(risk_score) as avg_risk
                FROM audit_log 
                WHERE created_at > DATE_SUB(NOW(), INTERVAL ?) AND risk_score >= 70
                GROUP BY action_type
                ORDER BY count DESC
                LIMIT 10
            ");
            $stmt->execute([$interval]);
            $stats['high_risk_actions'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Active sessions
            $stmt = $this->pdo->query("
                SELECT 
                    user_type,
                    COUNT(*) as active_sessions,
                    AVG(actions_count) as avg_actions,
                    AVG(risk_events_count) as avg_risk_events
                FROM user_sessions 
                WHERE is_active = TRUE
                GROUP BY user_type
            ");
            $stats['active_sessions'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Data changes summary
            $stmt = $this->pdo->query("
                SELECT 
                    dc.table_name,
                    dc.change_type,
                    COUNT(*) as count
                FROM data_changes dc
                JOIN audit_log al ON dc.audit_log_id = al.id
                WHERE al.created_at > $cutoffTime
                GROUP BY dc.table_name, dc.change_type
                ORDER BY count DESC
            ");
            $stats['data_changes'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            return $stats;
            
        } catch (Exception $e) {
            error_log("AuditLogger: getAuditStatistics error - " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Generate compliance report
     */
    public function generateComplianceReport($startDate, $endDate) {
        try {
            $report = [];
            
            // GDPR compliance events
            $stmt = $this->pdo->prepare("
                SELECT 
                    ce.compliance_type,
                    ce.requirement_met,
                    COUNT(*) as count,
                    al.action_type,
                    al.description
                FROM compliance_events ce
                JOIN audit_log al ON ce.audit_log_id = al.id
                WHERE al.created_at BETWEEN ? AND ?
                GROUP BY ce.compliance_type, ce.requirement_met, al.action_type
                ORDER BY ce.compliance_type, count DESC
            ");
            $stmt->execute([$startDate, $endDate]);
            $report['compliance_events'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Data access patterns
            $stmt = $this->pdo->prepare("
                SELECT 
                    resource_type,
                    action_type,
                    COUNT(*) as access_count,
                    COUNT(DISTINCT user_id) as unique_users,
                    AVG(risk_score) as avg_risk
                FROM audit_log
                WHERE created_at BETWEEN ? AND ? 
                AND action_category = 'data_access'
                GROUP BY resource_type, action_type
                ORDER BY access_count DESC
            ");
            $stmt->execute([$startDate, $endDate]);
            $report['data_access_patterns'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Authentication events
            $stmt = $this->pdo->prepare("
                SELECT 
                    result,
                    COUNT(*) as count,
                    COUNT(DISTINCT ip_address) as unique_ips
                FROM audit_log
                WHERE created_at BETWEEN ? AND ? 
                AND action_category = 'authentication'
                GROUP BY result
            ");
            $stmt->execute([$startDate, $endDate]);
            $report['authentication_summary'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            return $report;
            
        } catch (Exception $e) {
            error_log("AuditLogger: generateComplianceReport error - " . $e->getMessage());
            return [];
        }
    }
}
?>
