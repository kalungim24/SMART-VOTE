<?php
/**
 * SmartVote Security Manager
 * Handles security features like brute force protection, CSRF tokens, etc.
 */

class SecurityManager {
    private $pdo;
    private $maxLoginAttempts = 5;
    private $lockoutDuration = 900; // 15 minutes
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->initSecurityTables();
    }
    
    /**
     * Initialize security-related database tables
     */
    private function initSecurityTables() {
        try {
            // Create login_attempts table if it doesn't exist
            $this->pdo->exec("
                CREATE TABLE IF NOT EXISTS login_attempts (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    ip_address VARCHAR(45) NOT NULL,
                    username VARCHAR(255) NOT NULL,
                    attempt_type ENUM('admin', 'voter') NOT NULL,
                    success BOOLEAN NOT NULL DEFAULT FALSE,
                    attempted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    user_agent TEXT,
                    INDEX idx_ip_type_time (ip_address, attempt_type, attempted_at),
                    INDEX idx_username_type_time (username, attempt_type, attempted_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
            
            // Create security_events table if it doesn't exist
            $this->pdo->exec("
                CREATE TABLE IF NOT EXISTS security_events (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    event_type VARCHAR(50) NOT NULL,
                    severity ENUM('low', 'medium', 'high', 'critical') NOT NULL,
                    ip_address VARCHAR(45) NOT NULL,
                    user_id INT NULL,
                    user_type ENUM('admin', 'voter') NULL,
                    description TEXT NOT NULL,
                    metadata JSON NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_event_type_time (event_type, created_at),
                    INDEX idx_severity_time (severity, created_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
            
        } catch (Exception $e) {
            error_log("SecurityManager: Failed to initialize tables - " . $e->getMessage());
        }
    }
    
    /**
     * Check if IP/username is currently locked out
     */
    public function isLockedOut($username, $userType, $ipAddress = null) {
        try {
            $ipAddress = $ipAddress ?: $this->getClientIP();
            
            // Check lockout by IP
            $ipLockout = $this->checkIPLockout($ipAddress, $userType);
            if ($ipLockout) return $ipLockout;
            
            // Check lockout by username
            $userLockout = $this->checkUsernameLockout($username, $userType);
            if ($userLockout) return $userLockout;
            
            return false;
            
        } catch (Exception $e) {
            error_log("SecurityManager: isLockedOut error - " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Check IP-based lockout
     */
    private function checkIPLockout($ipAddress, $userType) {
        $cutoffTime = date('Y-m-d H:i:s', time() - $this->lockoutDuration);
        
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) as failed_attempts, 
                   MAX(attempted_at) as last_attempt
            FROM login_attempts 
            WHERE ip_address = ? 
            AND attempt_type = ? 
            AND success = FALSE 
            AND attempted_at > ?
        ");
        $stmt->execute([$ipAddress, $userType, $cutoffTime]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result['failed_attempts'] >= $this->maxLoginAttempts) {
            $timeRemaining = strtotime($result['last_attempt']) + $this->lockoutDuration - time();
            return [
                'locked' => true,
                'reason' => 'IP address temporarily locked due to multiple failed login attempts',
                'time_remaining' => max(0, $timeRemaining),
                'unlock_time' => date('H:i:s', time() + $timeRemaining)
            ];
        }
        
        return false;
    }
    
    /**
     * Check username-based lockout
     */
    private function checkUsernameLockout($username, $userType) {
        $cutoffTime = date('Y-m-d H:i:s', time() - $this->lockoutDuration);
        
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) as failed_attempts, 
                   MAX(attempted_at) as last_attempt
            FROM login_attempts 
            WHERE username = ? 
            AND attempt_type = ? 
            AND success = FALSE 
            AND attempted_at > ?
        ");
        $stmt->execute([$username, $userType, $cutoffTime]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result['failed_attempts'] >= $this->maxLoginAttempts) {
            $timeRemaining = strtotime($result['last_attempt']) + $this->lockoutDuration - time();
            return [
                'locked' => true,
                'reason' => 'Account temporarily locked due to multiple failed login attempts',
                'time_remaining' => max(0, $timeRemaining),
                'unlock_time' => date('H:i:s', time() + $timeRemaining)
            ];
        }
        
        return false;
    }
    
    /**
     * Record login attempt
     */
    public function recordLoginAttempt($username, $userType, $success, $ipAddress = null) {
        try {
            $ipAddress = $ipAddress ?: $this->getClientIP();
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
            
            $stmt = $this->pdo->prepare("
                INSERT INTO login_attempts (ip_address, username, attempt_type, success, user_agent) 
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([$ipAddress, $username, $userType, $success, $userAgent]);
            
            // Log security event if failed
            if (!$success) {
                $this->logSecurityEvent('failed_login', 'medium', [
                    'username' => $username,
                    'user_type' => $userType,
                    'user_agent' => $userAgent
                ], "Failed login attempt for $userType: $username");
            } else {
                // Clear old failed attempts on successful login
                $this->clearFailedAttempts($username, $userType, $ipAddress);
                
                $this->logSecurityEvent('successful_login', 'low', [
                    'username' => $username,
                    'user_type' => $userType
                ], "Successful login for $userType: $username");
            }
            
        } catch (Exception $e) {
            error_log("SecurityManager: recordLoginAttempt error - " . $e->getMessage());
        }
    }
    
    /**
     * Clear failed attempts after successful login
     */
    private function clearFailedAttempts($username, $userType, $ipAddress) {
        try {
            // Clear username-based failed attempts
            $stmt = $this->pdo->prepare("
                DELETE FROM login_attempts 
                WHERE username = ? AND attempt_type = ? AND success = FALSE
            ");
            $stmt->execute([$username, $userType]);
            
            // Clear IP-based failed attempts for this user type
            $stmt = $this->pdo->prepare("
                DELETE FROM login_attempts 
                WHERE ip_address = ? AND attempt_type = ? AND success = FALSE
            ");
            $stmt->execute([$ipAddress, $userType]);
            
        } catch (Exception $e) {
            error_log("SecurityManager: clearFailedAttempts error - " . $e->getMessage());
        }
    }
    
    /**
     * Generate CSRF token
     */
    public function generateCSRFToken() {
        if (!isset($_SESSION['csrf_tokens'])) {
            $_SESSION['csrf_tokens'] = [];
        }
        
        $token = bin2hex(random_bytes(32));
        $tokenData = [
            'token' => $token,
            'created' => time(),
            'expires' => time() + 3600 // 1 hour
        ];
        
        $_SESSION['csrf_tokens'][$token] = $tokenData;
        
        // Clean up expired tokens
        $this->cleanupExpiredTokens();
        
        return $token;
    }
    
    /**
     * Validate CSRF token
     */
    public function validateCSRFToken($token) {
        if (!isset($_SESSION['csrf_tokens'][$token])) {
            return false;
        }
        
        $tokenData = $_SESSION['csrf_tokens'][$token];
        
        // Check if token is expired
        if (time() > $tokenData['expires']) {
            unset($_SESSION['csrf_tokens'][$token]);
            return false;
        }
        
        // Token is valid - remove it (one-time use)
        unset($_SESSION['csrf_tokens'][$token]);
        return true;
    }
    
    /**
     * Clean up expired CSRF tokens
     */
    private function cleanupExpiredTokens() {
        if (!isset($_SESSION['csrf_tokens'])) return;
        
        $currentTime = time();
        foreach ($_SESSION['csrf_tokens'] as $token => $data) {
            if ($currentTime > $data['expires']) {
                unset($_SESSION['csrf_tokens'][$token]);
            }
        }
    }
    
    /**
     * Validate password strength
     */
    public function validatePasswordStrength($password) {
        $errors = [];
        
        // Minimum length
        if (strlen($password) < 8) {
            $errors[] = "Password must be at least 8 characters long";
        }
        
        // Maximum length
        if (strlen($password) > 128) {
            $errors[] = "Password must be less than 128 characters long";
        }
        
        // Must contain uppercase
        if (!preg_match('/[A-Z]/', $password)) {
            $errors[] = "Password must contain at least one uppercase letter";
        }
        
        // Must contain lowercase
        if (!preg_match('/[a-z]/', $password)) {
            $errors[] = "Password must contain at least one lowercase letter";
        }
        
        // Must contain number
        if (!preg_match('/[0-9]/', $password)) {
            $errors[] = "Password must contain at least one number";
        }
        
        // Must contain special character
        if (!preg_match('/[^A-Za-z0-9]/', $password)) {
            $errors[] = "Password must contain at least one special character";
        }
        
        // Check for common weak passwords
        $weakPasswords = [
            'password', 'password123', '12345678', 'qwerty', 'admin', 'administrator',
            'root', 'user', '123456789', 'password1', 'admin123', 'test123'
        ];
        
        if (in_array(strtolower($password), $weakPasswords)) {
            $errors[] = "Password is too common and easily guessed";
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'strength' => $this->calculatePasswordStrength($password)
        ];
    }
    
    /**
     * Calculate password strength score
     */
    private function calculatePasswordStrength($password) {
        $score = 0;
        
        // Length bonus
        $score += min(strlen($password) * 2, 50);
        
        // Character variety
        if (preg_match('/[a-z]/', $password)) $score += 5;
        if (preg_match('/[A-Z]/', $password)) $score += 5;
        if (preg_match('/[0-9]/', $password)) $score += 5;
        if (preg_match('/[^A-Za-z0-9]/', $password)) $score += 10;
        
        // Pattern penalties
        if (preg_match('/(.)\1{2,}/', $password)) $score -= 10; // Repeated characters
        if (preg_match('/123|abc|qwe|asd/i', $password)) $score -= 5; // Sequential patterns
        
        $score = max(0, min(100, $score));
        
        if ($score >= 80) return 'very-strong';
        if ($score >= 60) return 'strong';
        if ($score >= 40) return 'medium';
        if ($score >= 20) return 'weak';
        return 'very-weak';
    }
    
    /**
     * Log security event
     */
    public function logSecurityEvent($eventType, $severity, $metadata = [], $description = '') {
        try {
            $ipAddress = $this->getClientIP();
            $userId = $_SESSION['user_id'] ?? $_SESSION['voter_pk'] ?? null;
            $userType = $_SESSION['user_role'] ?? null;
            
            $stmt = $this->pdo->prepare("
                INSERT INTO security_events (event_type, severity, ip_address, user_id, user_type, description, metadata) 
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $eventType,
                $severity,
                $ipAddress,
                $userId,
                $userType,
                $description,
                json_encode($metadata)
            ]);
            
        } catch (Exception $e) {
            error_log("SecurityManager: logSecurityEvent error - " . $e->getMessage());
        }
    }
    
    /**
     * Get client IP address
     */
    public function getClientIP() {
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
     * Set security headers
     */
    public function setSecurityHeaders() {
        // Prevent clickjacking
        header('X-Frame-Options: DENY');
        
        // XSS protection
        header('X-XSS-Protection: 1; mode=block');
        
        // Content type sniffing protection
        header('X-Content-Type-Options: nosniff');
        
        // Referrer policy
        header('Referrer-Policy: strict-origin-when-cross-origin');
        
        // Content Security Policy
        header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' https://cdn.tailwindcss.com https://cdn.jsdelivr.net; style-src 'self' 'unsafe-inline' https://cdn.tailwindcss.com; font-src 'self' data:; img-src 'self' data: https:; connect-src 'self'");
        
        // HTTPS enforcement (only if HTTPS is available)
        if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        }
        
        // Permissions policy
        header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
    }
    
    /**
     * Get login attempt statistics
     */
    public function getLoginAttemptStats($hours = 24) {
        try {
            $cutoffTime = date('Y-m-d H:i:s', time() - ($hours * 3600));
            
            $stmt = $this->pdo->prepare("
                SELECT 
                    attempt_type,
                    success,
                    COUNT(*) as count,
                    COUNT(DISTINCT ip_address) as unique_ips,
                    COUNT(DISTINCT username) as unique_users
                FROM login_attempts 
                WHERE attempted_at > ? 
                GROUP BY attempt_type, success
                ORDER BY attempt_type, success
            ");
            $stmt->execute([$cutoffTime]);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            error_log("SecurityManager: getLoginAttemptStats error - " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get security events summary
     */
    public function getSecurityEventsSummary($hours = 24) {
        try {
            $cutoffTime = date('Y-m-d H:i:s', time() - ($hours * 3600));
            
            $stmt = $this->pdo->prepare("
                SELECT 
                    event_type,
                    severity,
                    COUNT(*) as count,
                    COUNT(DISTINCT ip_address) as unique_ips
                FROM security_events 
                WHERE created_at > ? 
                GROUP BY event_type, severity
                ORDER BY severity DESC, count DESC
            ");
            $stmt->execute([$cutoffTime]);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            error_log("SecurityManager: getSecurityEventsSummary error - " . $e->getMessage());
            return [];
        }
    }
}
?>
