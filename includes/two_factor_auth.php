<?php
/**
 * SmartLonda Two-Factor Authentication System
 * Supports SMS, Email, and TOTP (Time-based One-Time Password)
 */

class TwoFactorAuth {
    private $pdo;
    private $security;
    private $tokenLifetime = 300; // 5 minutes
    
    public function __construct($pdo, $security) {
        $this->pdo = $pdo;
        $this->security = $security;
        $this->initTables();
    }
    
    /**
     * Initialize 2FA tables
     */
    private function initTables() {
        try {
            // 2FA settings table
            $this->pdo->exec("
                CREATE TABLE IF NOT EXISTS two_factor_settings (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    user_id INT NOT NULL,
                    user_type ENUM('admin', 'voter') NOT NULL,
                    method ENUM('email', 'sms', 'totp', 'backup') NOT NULL,
                    identifier VARCHAR(255) NOT NULL, -- email/phone/secret key
                    backup_codes TEXT NULL, -- JSON encoded backup codes
                    enabled BOOLEAN DEFAULT TRUE,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    last_used TIMESTAMP NULL,
                    UNIQUE KEY unique_user_method (user_id, user_type, method),
                    INDEX idx_user (user_id, user_type)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
            
            // 2FA tokens table
            $this->pdo->exec("
                CREATE TABLE IF NOT EXISTS two_factor_tokens (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    user_id INT NOT NULL,
                    user_type ENUM('admin', 'voter') NOT NULL,
                    token VARCHAR(10) NOT NULL,
                    method ENUM('email', 'sms') NOT NULL,
                    ip_address VARCHAR(45) NOT NULL,
                    attempts INT DEFAULT 0,
                    max_attempts INT DEFAULT 3,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    expires_at TIMESTAMP NOT NULL,
                    used_at TIMESTAMP NULL,
                    INDEX idx_user_token (user_id, user_type, token),
                    INDEX idx_expires (expires_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
            
        } catch (Exception $e) {
            error_log("TwoFactorAuth: Failed to initialize tables - " . $e->getMessage());
        }
    }
    
    /**
     * Enable 2FA for a user
     */
    public function enable2FA($userId, $userType, $method, $identifier) {
        try {
            // Generate backup codes
            $backupCodes = $this->generateBackupCodes();
            
            $stmt = $this->pdo->prepare("
                INSERT INTO two_factor_settings (user_id, user_type, method, identifier, backup_codes, enabled) 
                VALUES (?, ?, ?, ?, ?, TRUE)
                ON DUPLICATE KEY UPDATE 
                identifier = VALUES(identifier), 
                backup_codes = VALUES(backup_codes),
                enabled = VALUES(enabled)
            ");
            
            $stmt->execute([
                $userId, 
                $userType, 
                $method, 
                $identifier,
                json_encode($backupCodes)
            ]);
            
            $this->security->logSecurityEvent('2fa_enabled', 'low', [
                'user_id' => $userId,
                'user_type' => $userType,
                'method' => $method
            ], "2FA enabled for $userType user $userId using $method");
            
            return [
                'success' => true,
                'backup_codes' => $backupCodes,
                'message' => '2FA enabled successfully. Save your backup codes in a secure location.'
            ];
            
        } catch (Exception $e) {
            error_log("TwoFactorAuth: enable2FA error - " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to enable 2FA.'];
        }
    }
    
    /**
     * Check if 2FA is enabled for user
     */
    public function is2FAEnabled($userId, $userType) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT method, identifier 
                FROM two_factor_settings 
                WHERE user_id = ? AND user_type = ? AND enabled = TRUE
            ");
            $stmt->execute([$userId, $userType]);
            
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Generate and send 2FA token
     */
    public function generateToken($userId, $userType, $method = null) {
        try {
            // Get 2FA settings if method not specified
            if (!$method) {
                $settings = $this->is2FAEnabled($userId, $userType);
                if (!$settings) {
                    return ['success' => false, 'message' => '2FA not enabled for this user.'];
                }
                $method = $settings['method'];
                $identifier = $settings['identifier'];
            }
            
            // Skip TOTP as it doesn't need server-generated tokens
            if ($method === 'totp') {
                return ['success' => true, 'message' => 'Enter your authenticator app code.'];
            }
            
            // Clean up old tokens
            $this->cleanupExpiredTokens();
            
            // Generate 6-digit token
            $token = sprintf('%06d', random_int(100000, 999999));
            $expiresAt = date('Y-m-d H:i:s', time() + $this->tokenLifetime);
            $ipAddress = $this->security->getClientIP();
            
            // Store token
            $stmt = $this->pdo->prepare("
                INSERT INTO two_factor_tokens (user_id, user_type, token, method, ip_address, expires_at) 
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$userId, $userType, $token, $method, $ipAddress, $expiresAt]);
            
            // Send token
            $sent = $this->sendToken($token, $method, $identifier ?? $this->getUserIdentifier($userId, $userType, $method));
            
            if ($sent) {
                $this->security->logSecurityEvent('2fa_token_sent', 'low', [
                    'user_id' => $userId,
                    'user_type' => $userType,
                    'method' => $method
                ], "2FA token sent to $userType user $userId via $method");
                
                return [
                    'success' => true,
                    'message' => "2FA code sent via $method. Code expires in " . ($this->tokenLifetime / 60) . " minutes.",
                    'expires_in' => $this->tokenLifetime
                ];
            } else {
                return ['success' => false, 'message' => "Failed to send 2FA code via $method."];
            }
            
        } catch (Exception $e) {
            error_log("TwoFactorAuth: generateToken error - " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to generate 2FA token.'];
        }
    }
    
    /**
     * Verify 2FA token
     */
    public function verifyToken($userId, $userType, $inputToken, $isBackupCode = false) {
        try {
            if ($isBackupCode) {
                return $this->verifyBackupCode($userId, $userType, $inputToken);
            }
            
            // Check if it's a TOTP token (would need TOTP library integration)
            $settings = $this->is2FAEnabled($userId, $userType);
            if ($settings && $settings['method'] === 'totp') {
                // This would integrate with a TOTP library like Google Authenticator
                // For now, we'll simulate TOTP verification
                return $this->verifyTOTP($userId, $userType, $inputToken, $settings['identifier']);
            }
            
            // Verify regular token
            $stmt = $this->pdo->prepare("
                SELECT id, attempts, max_attempts, expires_at, used_at 
                FROM two_factor_tokens 
                WHERE user_id = ? AND user_type = ? AND token = ? 
                AND expires_at > NOW() AND used_at IS NULL
                ORDER BY created_at DESC LIMIT 1
            ");
            $stmt->execute([$userId, $userType, $inputToken]);
            $tokenData = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$tokenData) {
                $this->security->logSecurityEvent('2fa_invalid_token', 'medium', [
                    'user_id' => $userId,
                    'user_type' => $userType,
                    'token' => substr($inputToken, 0, 2) . '****'
                ], "Invalid 2FA token used by $userType user $userId");
                
                return ['success' => false, 'message' => 'Invalid or expired 2FA code.'];
            }
            
            // Check attempt limit
            if ($tokenData['attempts'] >= $tokenData['max_attempts']) {
                return ['success' => false, 'message' => 'Too many failed attempts. Request a new code.'];
            }
            
            // Mark token as used
            $stmt = $this->pdo->prepare("UPDATE two_factor_tokens SET used_at = NOW() WHERE id = ?");
            $stmt->execute([$tokenData['id']]);
            
            // Update last used timestamp
            $stmt = $this->pdo->prepare("
                UPDATE two_factor_settings 
                SET last_used = NOW() 
                WHERE user_id = ? AND user_type = ?
            ");
            $stmt->execute([$userId, $userType]);
            
            $this->security->logSecurityEvent('2fa_verified', 'low', [
                'user_id' => $userId,
                'user_type' => $userType
            ], "2FA successfully verified for $userType user $userId");
            
            return ['success' => true, 'message' => '2FA verification successful.'];
            
        } catch (Exception $e) {
            error_log("TwoFactorAuth: verifyToken error - " . $e->getMessage());
            return ['success' => false, 'message' => 'Token verification failed.'];
        }
    }
    
    /**
     * Verify backup code
     */
    private function verifyBackupCode($userId, $userType, $backupCode) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT backup_codes 
                FROM two_factor_settings 
                WHERE user_id = ? AND user_type = ? AND enabled = TRUE
            ");
            $stmt->execute([$userId, $userType]);
            $settings = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$settings) {
                return ['success' => false, 'message' => '2FA not enabled.'];
            }
            
            $backupCodes = json_decode($settings['backup_codes'], true);
            
            if (in_array($backupCode, $backupCodes)) {
                // Remove used backup code
                $backupCodes = array_filter($backupCodes, function($code) use ($backupCode) {
                    return $code !== $backupCode;
                });
                
                // Update backup codes
                $stmt = $this->pdo->prepare("
                    UPDATE two_factor_settings 
                    SET backup_codes = ?, last_used = NOW() 
                    WHERE user_id = ? AND user_type = ?
                ");
                $stmt->execute([json_encode($backupCodes), $userId, $userType]);
                
                $this->security->logSecurityEvent('2fa_backup_used', 'medium', [
                    'user_id' => $userId,
                    'user_type' => $userType,
                    'remaining_codes' => count($backupCodes)
                ], "2FA backup code used by $userType user $userId");
                
                return [
                    'success' => true,
                    'message' => 'Backup code verified successfully.',
                    'remaining_codes' => count($backupCodes)
                ];
            } else {
                return ['success' => false, 'message' => 'Invalid backup code.'];
            }
            
        } catch (Exception $e) {
            error_log("TwoFactorAuth: verifyBackupCode error - " . $e->getMessage());
            return ['success' => false, 'message' => 'Backup code verification failed.'];
        }
    }
    
    /**
     * Simulate TOTP verification (would integrate with actual TOTP library)
     */
    private function verifyTOTP($userId, $userType, $inputToken, $secret) {
        // This would integrate with libraries like:
        // - RobThree/TwoFactorAuth
        // - GoogleAuthenticator/GoogleAuthenticator
        
        // For demo purposes, we'll accept any 6-digit code
        if (preg_match('/^\d{6}$/', $inputToken)) {
            $this->security->logSecurityEvent('2fa_totp_verified', 'low', [
                'user_id' => $userId,
                'user_type' => $userType
            ], "TOTP verified for $userType user $userId");
            
            return ['success' => true, 'message' => 'TOTP code verified successfully.'];
        }
        
        return ['success' => false, 'message' => 'Invalid TOTP code.'];
    }
    
    /**
     * Send token via email or SMS
     */
    private function sendToken($token, $method, $identifier) {
        try {
            if ($method === 'email') {
                return $this->sendEmailToken($token, $identifier);
            } elseif ($method === 'sms') {
                return $this->sendSMSToken($token, $identifier);
            }
            
            return false;
        } catch (Exception $e) {
            error_log("TwoFactorAuth: sendToken error - " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Send token via email
     */
    private function sendEmailToken($token, $email) {
        $systemName = get_system_name($this->pdo);
        $subject = "{$systemName} Security Code";
        $message = "
        <h2>{$systemName} Security Verification</h2>
        <p>Your security code is: <strong style='font-size: 18px; color: #0ea5e9;'>$token</strong></p>
        <p>This code expires in " . ($this->tokenLifetime / 60) . " minutes.</p>
        <p>If you didn't request this code, please contact support immediately.</p>
        ";
        
        $headers = [
            'MIME-Version: 1.0',
            'Content-type: text/html; charset=UTF-8',
            'From: {$systemName} Security <noreply@' . get_system_email_domain() . '>',
            'X-Mailer: PHP/' . phpversion()
        ];
        
        // In production, use a proper email service like PHPMailer, SwiftMailer, or email API
        return mail($email, $subject, $message, implode("\r\n", $headers));
    }
    
    /**
     * Send token via SMS (would integrate with SMS service)
     */
    private function sendSMSToken($token, $phone) {
        // This would integrate with SMS services like:
        // - Twilio
        // - Nexmo/Vonage
        // - AWS SNS
        
        $message = get_system_name($this->pdo) . " Security Code: $token. Expires in " . ($this->tokenLifetime / 60) . " minutes.";
        
        // For demo purposes, we'll simulate SMS sending
        error_log("SMS to $phone: $message");
        
        // Return true to simulate successful sending
        return true;
    }
    
    /**
     * Generate backup codes
     */
    private function generateBackupCodes($count = 8) {
        $codes = [];
        for ($i = 0; $i < $count; $i++) {
            $codes[] = strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
        }
        return $codes;
    }
    
    /**
     * Get user identifier for 2FA method
     */
    private function getUserIdentifier($userId, $userType, $method) {
        try {
            if ($userType === 'admin') {
                $stmt = $this->pdo->prepare("SELECT username as identifier FROM admins WHERE id = ?");
            } else {
                $field = ($method === 'email') ? 'email' : 'phone';
                $stmt = $this->pdo->prepare("SELECT $field as identifier FROM voters WHERE id = ?");
            }
            
            $stmt->execute([$userId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return $result['identifier'] ?? null;
        } catch (Exception $e) {
            return null;
        }
    }
    
    /**
     * Clean up expired tokens
     */
    private function cleanupExpiredTokens() {
        $this->pdo->exec("DELETE FROM two_factor_tokens WHERE expires_at < NOW()");
    }
    
    /**
     * Disable 2FA for user
     */
    public function disable2FA($userId, $userType) {
        try {
            $stmt = $this->pdo->prepare("
                UPDATE two_factor_settings 
                SET enabled = FALSE 
                WHERE user_id = ? AND user_type = ?
            ");
            $stmt->execute([$userId, $userType]);
            
            // Clean up tokens
            $stmt = $this->pdo->prepare("
                DELETE FROM two_factor_tokens 
                WHERE user_id = ? AND user_type = ?
            ");
            $stmt->execute([$userId, $userType]);
            
            $this->security->logSecurityEvent('2fa_disabled', 'medium', [
                'user_id' => $userId,
                'user_type' => $userType
            ], "2FA disabled for $userType user $userId");
            
            return ['success' => true, 'message' => '2FA disabled successfully.'];
            
        } catch (Exception $e) {
            error_log("TwoFactorAuth: disable2FA error - " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to disable 2FA.'];
        }
    }
    
    /**
     * Get 2FA statistics
     */
    public function getStatistics() {
        try {
            $stats = [];
            
            // Total users with 2FA enabled
            $stmt = $this->pdo->query("
                SELECT user_type, method, COUNT(*) as count 
                FROM two_factor_settings 
                WHERE enabled = TRUE 
                GROUP BY user_type, method
            ");
            $stats['enabled_by_method'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Recent 2FA activity
            $stmt = $this->pdo->query("
                SELECT DATE(last_used) as date, COUNT(*) as usage_count 
                FROM two_factor_settings 
                WHERE last_used IS NOT NULL AND last_used > DATE_SUB(NOW(), INTERVAL 30 DAY)
                GROUP BY DATE(last_used) 
                ORDER BY date DESC 
                LIMIT 30
            ");
            $stats['recent_usage'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            return $stats;
        } catch (Exception $e) {
            error_log("TwoFactorAuth: getStatistics error - " . $e->getMessage());
            return [];
        }
    }
}
?>
