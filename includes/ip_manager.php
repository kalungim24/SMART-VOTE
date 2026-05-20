<?php
/**
 * SmartLonda IP Management System
 * Handles IP whitelisting, blacklisting, and geolocation-based access control
 */

class IPManager {
    private $pdo;
    private $security;
    
    public function __construct($pdo, $security) {
        $this->pdo = $pdo;
        $this->security = $security;
        $this->initTables();
    }
    
    /**
     * Initialize IP management tables
     */
    private function initTables() {
        try {
            // IP whitelist table
            $this->pdo->exec("
                CREATE TABLE IF NOT EXISTS ip_whitelist (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    ip_address VARCHAR(45) NOT NULL,
                    ip_range VARCHAR(50) NULL, -- for CIDR notation
                    description TEXT,
                    created_by INT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    expires_at TIMESTAMP NULL,
                    active BOOLEAN DEFAULT TRUE,
                    UNIQUE KEY unique_ip (ip_address),
                    INDEX idx_active (active),
                    INDEX idx_expires (expires_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
            
            // IP blacklist table
            $this->pdo->exec("
                CREATE TABLE IF NOT EXISTS ip_blacklist (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    ip_address VARCHAR(45) NOT NULL,
                    ip_range VARCHAR(50) NULL,
                    reason ENUM('manual', 'brute_force', 'suspicious', 'malware', 'spam') NOT NULL,
                    severity ENUM('low', 'medium', 'high', 'critical') DEFAULT 'medium',
                    description TEXT,
                    auto_generated BOOLEAN DEFAULT FALSE,
                    created_by INT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    expires_at TIMESTAMP NULL,
                    active BOOLEAN DEFAULT TRUE,
                    UNIQUE KEY unique_ip (ip_address),
                    INDEX idx_active_reason (active, reason),
                    INDEX idx_expires (expires_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
            
            // IP access logs
            $this->pdo->exec("
                CREATE TABLE IF NOT EXISTS ip_access_logs (
                    id BIGINT AUTO_INCREMENT PRIMARY KEY,
                    ip_address VARCHAR(45) NOT NULL,
                    country_code VARCHAR(2),
                    country_name VARCHAR(100),
                    region VARCHAR(100),
                    city VARCHAR(100),
                    user_agent TEXT,
                    request_uri VARCHAR(500),
                    request_method VARCHAR(10),
                    user_id INT NULL,
                    user_type ENUM('admin', 'voter', 'guest') NULL,
                    action_type VARCHAR(50),
                    status ENUM('allowed', 'blocked', 'suspicious') NOT NULL,
                    reason TEXT,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_ip_status (ip_address, status),
                    INDEX idx_created (created_at),
                    INDEX idx_user (user_id, user_type)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
            
            // Geolocation restrictions
            $this->pdo->exec("
                CREATE TABLE IF NOT EXISTS geo_restrictions (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    rule_name VARCHAR(100) NOT NULL,
                    rule_type ENUM('allow_countries', 'block_countries', 'allow_regions', 'block_regions') NOT NULL,
                    targets JSON NOT NULL, -- array of country codes or regions
                    applies_to ENUM('all', 'admin', 'voter') DEFAULT 'all',
                    active BOOLEAN DEFAULT TRUE,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_type_active (rule_type, active)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
            
        } catch (Exception $e) {
            error_log("IPManager: Failed to initialize tables - " . $e->getMessage());
        }
    }
    
    /**
     * Check if IP access should be allowed
     */
    public function checkIPAccess($ipAddress = null, $userType = 'guest', $action = 'access') {
        try {
            $ipAddress = $ipAddress ?: $this->getClientIP();
            
            // Log access attempt
            $this->logAccess($ipAddress, $userType, $action);
            
            // Check whitelist first (whitelist overrides everything)
            if ($this->isWhitelisted($ipAddress)) {
                $this->logAccessResult($ipAddress, 'allowed', 'IP is whitelisted', $userType, $action);
                return ['allowed' => true, 'reason' => 'IP whitelisted'];
            }
            
            // Check blacklist
            if ($blacklistEntry = $this->isBlacklisted($ipAddress)) {
                $this->logAccessResult($ipAddress, 'blocked', "IP blacklisted: {$blacklistEntry['reason']}", $userType, $action);
                $this->security->logSecurityEvent('ip_blacklist_block', 'high', [
                    'ip_address' => $ipAddress,
                    'user_type' => $userType,
                    'action' => $action,
                    'blacklist_reason' => $blacklistEntry['reason']
                ], "Blocked blacklisted IP $ipAddress attempting $action");
                
                return [
                    'allowed' => false,
                    'reason' => 'IP address is blacklisted',
                    'details' => $blacklistEntry['description'],
                    'severity' => $blacklistEntry['severity']
                ];
            }
            
            // Check geolocation restrictions
            $geoCheck = $this->checkGeolocationRestrictions($ipAddress, $userType);
            if (!$geoCheck['allowed']) {
                $this->logAccessResult($ipAddress, 'blocked', "Geolocation restriction: {$geoCheck['reason']}", $userType, $action);
                return $geoCheck;
            }
            
            // Check suspicious activity
            if ($this->isSuspiciousActivity($ipAddress, $userType, $action)) {
                $this->logAccessResult($ipAddress, 'suspicious', 'Suspicious activity pattern detected', $userType, $action);
                $this->security->logSecurityEvent('suspicious_ip_activity', 'medium', [
                    'ip_address' => $ipAddress,
                    'user_type' => $userType,
                    'action' => $action
                ], "Suspicious activity from IP $ipAddress");
                
                return [
                    'allowed' => true, // Allow but flag
                    'warning' => true,
                    'reason' => 'Suspicious activity detected - monitoring enhanced'
                ];
            }
            
            $this->logAccessResult($ipAddress, 'allowed', 'Normal access', $userType, $action);
            return ['allowed' => true, 'reason' => 'Normal access'];
            
        } catch (Exception $e) {
            error_log("IPManager: checkIPAccess error - " . $e->getMessage());
            // Fail open - allow access if system fails
            return ['allowed' => true, 'reason' => 'IP management system unavailable'];
        }
    }
    
    /**
     * Add IP to whitelist
     */
    public function whitelistIP($ipAddress, $description = '', $expiresAt = null, $createdBy = null) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO ip_whitelist (ip_address, description, expires_at, created_by) 
                VALUES (?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE 
                description = VALUES(description), 
                expires_at = VALUES(expires_at),
                active = TRUE
            ");
            
            $stmt->execute([$ipAddress, $description, $expiresAt, $createdBy]);
            
            $this->security->logSecurityEvent('ip_whitelisted', 'low', [
                'ip_address' => $ipAddress,
                'created_by' => $createdBy,
                'expires_at' => $expiresAt
            ], "IP $ipAddress added to whitelist");
            
            return ['success' => true, 'message' => "IP $ipAddress added to whitelist"];
            
        } catch (Exception $e) {
            error_log("IPManager: whitelistIP error - " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to whitelist IP'];
        }
    }
    
    /**
     * Add IP to blacklist
     */
    public function blacklistIP($ipAddress, $reason, $severity = 'medium', $description = '', $expiresAt = null, $createdBy = null, $autoGenerated = false) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO ip_blacklist (ip_address, reason, severity, description, expires_at, created_by, auto_generated) 
                VALUES (?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE 
                reason = VALUES(reason),
                severity = VALUES(severity),
                description = VALUES(description),
                expires_at = VALUES(expires_at),
                active = TRUE
            ");
            
            $stmt->execute([$ipAddress, $reason, $severity, $description, $expiresAt, $createdBy, $autoGenerated]);
            
            $this->security->logSecurityEvent('ip_blacklisted', 'high', [
                'ip_address' => $ipAddress,
                'reason' => $reason,
                'severity' => $severity,
                'auto_generated' => $autoGenerated,
                'created_by' => $createdBy
            ], "IP $ipAddress added to blacklist for $reason");
            
            return ['success' => true, 'message' => "IP $ipAddress added to blacklist"];
            
        } catch (Exception $e) {
            error_log("IPManager: blacklistIP error - " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to blacklist IP'];
        }
    }
    
    /**
     * Check if IP is whitelisted
     */
    public function isWhitelisted($ipAddress) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT * FROM ip_whitelist 
                WHERE ip_address = ? AND active = TRUE 
                AND (expires_at IS NULL OR expires_at > NOW())
            ");
            $stmt->execute([$ipAddress]);
            
            return $stmt->fetch(PDO::FETCH_ASSOC) !== false;
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Check if IP is blacklisted
     */
    public function isBlacklisted($ipAddress) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT reason, severity, description 
                FROM ip_blacklist 
                WHERE ip_address = ? AND active = TRUE 
                AND (expires_at IS NULL OR expires_at > NOW())
            ");
            $stmt->execute([$ipAddress]);
            
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Check geolocation restrictions
     */
    private function checkGeolocationRestrictions($ipAddress, $userType) {
        try {
            // Get IP geolocation (would integrate with service like IPGeolocation, MaxMind, etc.)
            $geoData = $this->getIPGeolocation($ipAddress);
            
            if (!$geoData) {
                return ['allowed' => true, 'reason' => 'Geolocation data unavailable'];
            }
            
            // Check active geo restrictions
            $stmt = $this->pdo->prepare("
                SELECT rule_name, rule_type, targets, applies_to
                FROM geo_restrictions 
                WHERE active = TRUE 
                AND (applies_to = 'all' OR applies_to = ?)
            ");
            $stmt->execute([$userType]);
            $restrictions = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($restrictions as $restriction) {
                $targets = json_decode($restriction['targets'], true);
                $countryCode = $geoData['country_code'];
                
                switch ($restriction['rule_type']) {
                    case 'allow_countries':
                        if (!in_array($countryCode, $targets)) {
                            return [
                                'allowed' => false,
                                'reason' => 'Country not in allowed list',
                                'details' => "Access from {$geoData['country_name']} is not permitted"
                            ];
                        }
                        break;
                        
                    case 'block_countries':
                        if (in_array($countryCode, $targets)) {
                            return [
                                'allowed' => false,
                                'reason' => 'Country is blocked',
                                'details' => "Access from {$geoData['country_name']} is blocked"
                            ];
                        }
                        break;
                }
            }
            
            return ['allowed' => true, 'reason' => 'Geolocation check passed'];
            
        } catch (Exception $e) {
            error_log("IPManager: checkGeolocationRestrictions error - " . $e->getMessage());
            return ['allowed' => true, 'reason' => 'Geolocation check failed, allowing access'];
        }
    }
    
    /**
     * Detect suspicious activity patterns
     */
    private function isSuspiciousActivity($ipAddress, $userType, $action) {
        try {
            $recentTime = date('Y-m-d H:i:s', time() - 3600); // Last hour
            
            // Check for rapid requests
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) as request_count
                FROM ip_access_logs 
                WHERE ip_address = ? AND created_at > ?
            ");
            $stmt->execute([$ipAddress, $recentTime]);
            $requestCount = $stmt->fetchColumn();
            
            // More than 100 requests per hour is suspicious
            if ($requestCount > 100) {
                return true;
            }
            
            // Check for multiple failed login attempts
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) as failed_attempts
                FROM ip_access_logs 
                WHERE ip_address = ? AND action_type = 'login' 
                AND status = 'blocked' AND created_at > ?
            ");
            $stmt->execute([$ipAddress, $recentTime]);
            $failedLogins = $stmt->fetchColumn();
            
            // More than 10 failed logins is suspicious
            if ($failedLogins > 10) {
                return true;
            }
            
            // Check for accessing multiple different endpoints rapidly
            $stmt = $this->pdo->prepare("
                SELECT COUNT(DISTINCT request_uri) as unique_endpoints
                FROM ip_access_logs 
                WHERE ip_address = ? AND created_at > ?
            ");
            $stmt->execute([$ipAddress, $recentTime]);
            $uniqueEndpoints = $stmt->fetchColumn();
            
            // Accessing more than 20 different endpoints rapidly is suspicious
            if ($uniqueEndpoints > 20) {
                return true;
            }
            
            return false;
            
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Get IP geolocation (mock implementation)
     */
    private function getIPGeolocation($ipAddress) {
        // In production, integrate with services like:
        // - IPGeolocation.io
        // - MaxMind GeoLite2
        // - IPStack
        // - ipapi.co
        
        // Mock data for demonstration
        if (filter_var($ipAddress, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return [
                'country_code' => 'US',
                'country_name' => 'United States',
                'region' => 'California',
                'city' => 'San Francisco',
                'latitude' => 37.7749,
                'longitude' => -122.4194
            ];
        }
        
        return null;
    }
    
    /**
     * Log IP access attempt
     */
    private function logAccess($ipAddress, $userType, $action) {
        try {
            $geoData = $this->getIPGeolocation($ipAddress);
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
            $requestUri = $_SERVER['REQUEST_URI'] ?? '';
            $requestMethod = $_SERVER['REQUEST_METHOD'] ?? '';
            $userId = $_SESSION['user_id'] ?? $_SESSION['voter_pk'] ?? null;
            
            $stmt = $this->pdo->prepare("
                INSERT INTO ip_access_logs (
                    ip_address, country_code, country_name, region, city,
                    user_agent, request_uri, request_method, user_id, user_type, action_type, status
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'allowed')
            ");
            
            $stmt->execute([
                $ipAddress,
                $geoData['country_code'] ?? null,
                $geoData['country_name'] ?? null,
                $geoData['region'] ?? null,
                $geoData['city'] ?? null,
                $userAgent,
                $requestUri,
                $requestMethod,
                $userId,
                $userType,
                $action
            ]);
            
        } catch (Exception $e) {
            error_log("IPManager: logAccess error - " . $e->getMessage());
        }
    }
    
    /**
     * Log access result
     */
    private function logAccessResult($ipAddress, $status, $reason, $userType, $action) {
        try {
            $stmt = $this->pdo->prepare("
                UPDATE ip_access_logs 
                SET status = ?, reason = ? 
                WHERE ip_address = ? AND action_type = ? 
                ORDER BY created_at DESC LIMIT 1
            ");
            $stmt->execute([$status, $reason, $ipAddress, $action]);
            
        } catch (Exception $e) {
            error_log("IPManager: logAccessResult error - " . $e->getMessage());
        }
    }
    
    /**
     * Auto-blacklist IP based on activity
     */
    public function autoBlacklistIP($ipAddress, $reason, $duration = 3600) {
        $expiresAt = date('Y-m-d H:i:s', time() + $duration);
        
        return $this->blacklistIP(
            $ipAddress, 
            $reason, 
            'medium', 
            "Auto-generated blacklist entry for $reason", 
            $expiresAt, 
            null, 
            true
        );
    }
    
    /**
     * Get IP management statistics
     */
    public function getStatistics($hours = 24) {
        try {
            $cutoffTime = date('Y-m-d H:i:s', time() - ($hours * 3600));
            
            $stats = [];
            
            // Access statistics
            $stmt = $this->pdo->prepare("
                SELECT 
                    status,
                    COUNT(*) as count,
                    COUNT(DISTINCT ip_address) as unique_ips
                FROM ip_access_logs 
                WHERE created_at > ?
                GROUP BY status
            ");
            $stmt->execute([$cutoffTime]);
            $stats['access_summary'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Top blocked IPs
            $stmt = $this->pdo->prepare("
                SELECT 
                    ip_address,
                    COUNT(*) as block_count,
                    MAX(created_at) as last_blocked
                FROM ip_access_logs 
                WHERE status = 'blocked' AND created_at > ?
                GROUP BY ip_address 
                ORDER BY block_count DESC 
                LIMIT 10
            ");
            $stmt->execute([$cutoffTime]);
            $stats['top_blocked_ips'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Country statistics
            $stmt = $this->pdo->prepare("
                SELECT 
                    country_name,
                    COUNT(*) as access_count,
                    COUNT(DISTINCT ip_address) as unique_ips
                FROM ip_access_logs 
                WHERE created_at > ? AND country_name IS NOT NULL
                GROUP BY country_name 
                ORDER BY access_count DESC 
                LIMIT 10
            ");
            $stmt->execute([$cutoffTime]);
            $stats['top_countries'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            return $stats;
            
        } catch (Exception $e) {
            error_log("IPManager: getStatistics error - " . $e->getMessage());
            return [];
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
     * Remove IP from whitelist
     */
    public function removeFromWhitelist($ipAddress) {
        try {
            $stmt = $this->pdo->prepare("UPDATE ip_whitelist SET active = FALSE WHERE ip_address = ?");
            $stmt->execute([$ipAddress]);
            
            return ['success' => true, 'message' => "IP $ipAddress removed from whitelist"];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Failed to remove IP from whitelist'];
        }
    }
    
    /**
     * Remove IP from blacklist
     */
    public function removeFromBlacklist($ipAddress) {
        try {
            $stmt = $this->pdo->prepare("UPDATE ip_blacklist SET active = FALSE WHERE ip_address = ?");
            $stmt->execute([$ipAddress]);
            
            $this->security->logSecurityEvent('ip_unblacklisted', 'low', [
                'ip_address' => $ipAddress
            ], "IP $ipAddress removed from blacklist");
            
            return ['success' => true, 'message' => "IP $ipAddress removed from blacklist"];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Failed to remove IP from blacklist'];
        }
    }
}
?>
