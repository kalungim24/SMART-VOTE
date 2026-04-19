<?php
/**
 * SmartVote Rate Limiter
 * Prevents API abuse, DoS attacks, and excessive resource usage
 */

class RateLimiter {
    private $pdo;
    private $defaultLimits = [
        'login' => ['requests' => 30, 'window' => 300], // 30 attempts per 5 minutes (increased for testing)
        'api' => ['requests' => 100, 'window' => 3600], // 100 requests per hour
        'form_submit' => ['requests' => 20, 'window' => 300], // 20 submissions per 5 minutes
        'password_reset' => ['requests' => 3, 'window' => 3600], // 3 resets per hour
        'registration' => ['requests' => 5, 'window' => 3600], // 5 registrations per hour
        'vote_cast' => ['requests' => 1, 'window' => 86400] // 1 vote per day (election period)
    ];
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->initRateLimitTable();
    }
    
    /**
     * Initialize rate limiting table
     */
    private function initRateLimitTable() {
        try {
            $this->pdo->exec("
                CREATE TABLE IF NOT EXISTS rate_limits (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    ip_address VARCHAR(45) NOT NULL,
                    user_id INT NULL,
                    action_type VARCHAR(50) NOT NULL,
                    request_count INT NOT NULL DEFAULT 1,
                    window_start TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    last_request TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    blocked_until TIMESTAMP NULL,
                    INDEX idx_ip_action_window (ip_address, action_type, window_start),
                    INDEX idx_user_action_window (user_id, action_type, window_start),
                    INDEX idx_blocked_until (blocked_until)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
        } catch (Exception $e) {
            error_log("RateLimiter: Failed to initialize table - " . $e->getMessage());
        }
    }
    
    /**
     * Check if request should be rate limited
     */
    public function checkLimit($actionType, $ipAddress = null, $userId = null) {
        try {
            $ipAddress = $ipAddress ?: $this->getClientIP();
            $limits = $this->defaultLimits[$actionType] ?? $this->defaultLimits['api'];
            
            // Clean up old entries first
            $this->cleanupOldEntries();
            
            // Check if currently blocked
            if ($this->isCurrentlyBlocked($actionType, $ipAddress, $userId)) {
                return [
                    'allowed' => false,
                    'reason' => 'Rate limit exceeded. You are temporarily blocked.',
                    'retry_after' => $this->getRetryAfterTime($actionType, $ipAddress, $userId),
                    'action' => $actionType
                ];
            }
            
            // Get current window data
            $windowStart = date('Y-m-d H:i:s', time() - $limits['window']);
            $currentCount = $this->getCurrentRequestCount($actionType, $ipAddress, $userId, $windowStart);
            
            // Check if limit exceeded
            if ($currentCount >= $limits['requests']) {
                // Block for extended period (2x the window)
                $blockUntil = date('Y-m-d H:i:s', time() + ($limits['window'] * 2));
                $this->blockAction($actionType, $ipAddress, $userId, $blockUntil);
                
                return [
                    'allowed' => false,
                    'reason' => "Rate limit exceeded. Maximum {$limits['requests']} {$actionType} requests per " . ($limits['window'] / 60) . " minutes.",
                    'retry_after' => $limits['window'] * 2,
                    'action' => $actionType,
                    'limit' => $limits['requests'],
                    'window' => $limits['window']
                ];
            }
            
            // Increment counter
            $this->recordRequest($actionType, $ipAddress, $userId);
            
            return [
                'allowed' => true,
                'remaining' => $limits['requests'] - $currentCount - 1,
                'reset_time' => time() + $limits['window'],
                'action' => $actionType
            ];
            
        } catch (Exception $e) {
            error_log("RateLimiter: checkLimit error - " . $e->getMessage());
            // Fail open - allow request if rate limiter fails
            return ['allowed' => true, 'error' => 'Rate limiter unavailable'];
        }
    }
    
    /**
     * Check if action is currently blocked
     */
    private function isCurrentlyBlocked($actionType, $ipAddress, $userId) {
        $sql = "SELECT blocked_until FROM rate_limits 
                WHERE action_type = ? AND ip_address = ?";
        $params = [$actionType, $ipAddress];
        
        if ($userId) {
            $sql .= " OR (action_type = ? AND user_id = ?)";
            $params = array_merge($params, [$actionType, $userId]);
        }
        
        $sql .= " AND blocked_until > NOW() ORDER BY blocked_until DESC LIMIT 1";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchColumn() !== false;
    }
    
    /**
     * Get retry after time for blocked action
     */
    private function getRetryAfterTime($actionType, $ipAddress, $userId) {
        $sql = "SELECT TIMESTAMPDIFF(SECOND, NOW(), blocked_until) as retry_seconds 
                FROM rate_limits 
                WHERE action_type = ? AND ip_address = ?";
        $params = [$actionType, $ipAddress];
        
        if ($userId) {
            $sql .= " OR (action_type = ? AND user_id = ?)";
            $params = array_merge($params, [$actionType, $userId]);
        }
        
        $sql .= " AND blocked_until > NOW() ORDER BY blocked_until DESC LIMIT 1";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        
        return max(0, $stmt->fetchColumn() ?: 0);
    }
    
    /**
     * Get current request count in window
     */
    private function getCurrentRequestCount($actionType, $ipAddress, $userId, $windowStart) {
        $sql = "SELECT COALESCE(SUM(request_count), 0) FROM rate_limits 
                WHERE action_type = ? AND ip_address = ? AND window_start >= ?";
        $params = [$actionType, $ipAddress, $windowStart];
        
        if ($userId) {
            $sql .= " OR (action_type = ? AND user_id = ? AND window_start >= ?)";
            $params = array_merge($params, [$actionType, $userId, $windowStart]);
        }
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        
        return (int)$stmt->fetchColumn();
    }
    
    /**
     * Record a request
     */
    private function recordRequest($actionType, $ipAddress, $userId) {
        // Try to update existing record in current window
        $windowStart = date('Y-m-d H:i:s', time() - 60); // 1-minute window for grouping
        
        $updateSql = "UPDATE rate_limits 
                      SET request_count = request_count + 1, last_request = NOW()
                      WHERE action_type = ? AND ip_address = ? AND window_start >= ?";
        $updateParams = [$actionType, $ipAddress, $windowStart];
        
        if ($userId) {
            $updateSql .= " OR (action_type = ? AND user_id = ? AND window_start >= ?)";
            $updateParams = array_merge($updateParams, [$actionType, $userId, $windowStart]);
        }
        
        $updateSql .= " LIMIT 1";
        
        $stmt = $this->pdo->prepare($updateSql);
        $stmt->execute($updateParams);
        
        // If no existing record updated, create new one
        if ($stmt->rowCount() == 0) {
            $insertSql = "INSERT INTO rate_limits (ip_address, user_id, action_type, request_count) 
                          VALUES (?, ?, ?, 1)";
            $stmt = $this->pdo->prepare($insertSql);
            $stmt->execute([$ipAddress, $userId, $actionType]);
        }
    }
    
    /**
     * Block an action
     */
    private function blockAction($actionType, $ipAddress, $userId, $blockUntil) {
        $sql = "INSERT INTO rate_limits (ip_address, user_id, action_type, request_count, blocked_until) 
                VALUES (?, ?, ?, 0, ?) 
                ON DUPLICATE KEY UPDATE blocked_until = VALUES(blocked_until)";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$ipAddress, $userId, $actionType, $blockUntil]);
    }
    
    /**
     * Clean up old entries
     */
    private function cleanupOldEntries() {
        // Clean entries older than 24 hours
        $this->pdo->exec("
            DELETE FROM rate_limits 
            WHERE window_start < DATE_SUB(NOW(), INTERVAL 24 HOUR) 
            AND (blocked_until IS NULL OR blocked_until < NOW())
        ");
    }
    
    /**
     * Set custom limit for specific action
     */
    public function setCustomLimit($actionType, $requests, $windowSeconds) {
        $this->defaultLimits[$actionType] = [
            'requests' => $requests,
            'window' => $windowSeconds
        ];
    }
    
    /**
     * Get rate limit status for action
     */
    public function getStatus($actionType, $ipAddress = null, $userId = null) {
        $ipAddress = $ipAddress ?: $this->getClientIP();
        $limits = $this->defaultLimits[$actionType] ?? $this->defaultLimits['api'];
        
        $windowStart = date('Y-m-d H:i:s', time() - $limits['window']);
        $currentCount = $this->getCurrentRequestCount($actionType, $ipAddress, $userId, $windowStart);
        $isBlocked = $this->isCurrentlyBlocked($actionType, $ipAddress, $userId);
        
        return [
            'action' => $actionType,
            'limit' => $limits['requests'],
            'window_seconds' => $limits['window'],
            'current_count' => $currentCount,
            'remaining' => max(0, $limits['requests'] - $currentCount),
            'is_blocked' => $isBlocked,
            'retry_after' => $isBlocked ? $this->getRetryAfterTime($actionType, $ipAddress, $userId) : 0,
            'reset_time' => time() + $limits['window']
        ];
    }
    
    /**
     * Whitelist an IP address (bypass rate limiting)
     */
    public function whitelistIP($ipAddress, $reason = '') {
        try {
            $this->pdo->exec("
                CREATE TABLE IF NOT EXISTS ip_whitelist (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    ip_address VARCHAR(45) NOT NULL UNIQUE,
                    reason TEXT,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_ip (ip_address)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
            
            $stmt = $this->pdo->prepare("
                INSERT INTO ip_whitelist (ip_address, reason) VALUES (?, ?)
                ON DUPLICATE KEY UPDATE reason = VALUES(reason)
            ");
            $stmt->execute([$ipAddress, $reason]);
            
            return true;
        } catch (Exception $e) {
            error_log("RateLimiter: whitelistIP error - " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Check if IP is whitelisted
     */
    public function isWhitelisted($ipAddress = null) {
        try {
            $ipAddress = $ipAddress ?: $this->getClientIP();
            
            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM ip_whitelist WHERE ip_address = ?");
            $stmt->execute([$ipAddress]);
            
            return $stmt->fetchColumn() > 0;
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Get client IP address
     */
    private function getClientIP() {
        $ipKeys = ['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'];
        
        foreach ($ipKeys as $key) {
            if (!empty($_SERVER[$key])) {
                $ips = explode(',', $_SERVER[$key]);
                $ip = trim($ips[0]);
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
    
    /**
     * Get rate limiting statistics
     */
    public function getStatistics($hours = 24) {
        try {
            $cutoffTime = date('Y-m-d H:i:s', time() - ($hours * 3600));
            
            $stmt = $this->pdo->prepare("
                SELECT 
                    action_type,
                    COUNT(*) as total_requests,
                    COUNT(DISTINCT ip_address) as unique_ips,
                    COUNT(DISTINCT user_id) as unique_users,
                    SUM(CASE WHEN blocked_until IS NOT NULL THEN 1 ELSE 0 END) as blocked_requests,
                    AVG(request_count) as avg_requests_per_session
                FROM rate_limits 
                WHERE window_start > ? 
                GROUP BY action_type
                ORDER BY total_requests DESC
            ");
            $stmt->execute([$cutoffTime]);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("RateLimiter: getStatistics error - " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get blocked IPs and users
     */
    public function getBlockedEntities() {
        try {
            $stmt = $this->pdo->query("
                SELECT 
                    ip_address,
                    user_id,
                    action_type,
                    blocked_until,
                    TIMESTAMPDIFF(SECOND, NOW(), blocked_until) as seconds_remaining
                FROM rate_limits 
                WHERE blocked_until > NOW()
                ORDER BY blocked_until ASC
            ");
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("RateLimiter: getBlockedEntities error - " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Manually unblock an IP or user
     */
    public function unblock($actionType = null, $ipAddress = null, $userId = null) {
        try {
            $sql = "UPDATE rate_limits SET blocked_until = NULL WHERE 1=1";
            $params = [];
            
            if ($actionType) {
                $sql .= " AND action_type = ?";
                $params[] = $actionType;
            }
            
            if ($ipAddress) {
                $sql .= " AND ip_address = ?";
                $params[] = $ipAddress;
            }
            
            if ($userId) {
                $sql .= " AND user_id = ?";
                $params[] = $userId;
            }
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            
            return $stmt->rowCount();
        } catch (Exception $e) {
            error_log("RateLimiter: unblock error - " . $e->getMessage());
            return 0;
        }
    }
}
?>
