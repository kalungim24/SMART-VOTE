<?php
/**
 * SmartVote Data Encryption System
 * Provides field-level encryption for sensitive data
 */

class DataEncryption {
    private $pdo;
    private $security;
    private $encryptionKey;
    private $cipher = 'AES-256-GCM';
    
    public function __construct($pdo, $security) {
        $this->pdo = $pdo;
        $this->security = $security;
        $this->initEncryption();
    }
    
    /**
     * Initialize encryption system
     */
    private function initEncryption() {
        try {
            // Get or generate encryption key
            $this->encryptionKey = $this->getOrCreateEncryptionKey();
            
            // Create encrypted data tracking table
            $this->pdo->exec("
                CREATE TABLE IF NOT EXISTS encrypted_fields (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    table_name VARCHAR(100) NOT NULL,
                    field_name VARCHAR(100) NOT NULL,
                    record_id VARCHAR(100) NOT NULL,
                    encryption_method VARCHAR(50) NOT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    UNIQUE KEY unique_field (table_name, field_name, record_id),
                    INDEX idx_table_record (table_name, record_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
            
            // Create encryption audit log
            $this->pdo->exec("
                CREATE TABLE IF NOT EXISTS encryption_audit (
                    id BIGINT AUTO_INCREMENT PRIMARY KEY,
                    operation ENUM('encrypt', 'decrypt', 'key_rotation') NOT NULL,
                    table_name VARCHAR(100) NOT NULL,
                    field_name VARCHAR(100) NOT NULL,
                    record_id VARCHAR(100) NOT NULL,
                    user_id INT NULL,
                    user_type ENUM('admin', 'voter', 'system') NOT NULL,
                    ip_address VARCHAR(45),
                    success BOOLEAN NOT NULL,
                    error_message TEXT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_operation_time (operation, created_at),
                    INDEX idx_table_field (table_name, field_name)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
            
        } catch (Exception $e) {
            error_log("DataEncryption: Failed to initialize - " . $e->getMessage());
        }
    }
    
    /**
     * Get or create master encryption key
     */
    private function getOrCreateEncryptionKey() {
        try {
            // Check if key file exists
            $keyFile = __DIR__ . '/../.encryption_key';
            
            if (file_exists($keyFile) && is_readable($keyFile)) {
                $key = file_get_contents($keyFile);
                if (strlen($key) === 64) { // 32 bytes = 64 hex chars
                    return hex2bin($key);
                }
            }
            
            // Generate new key
            $key = random_bytes(32); // 256-bit key
            $hexKey = bin2hex($key);
            
            // Save key securely
            if (file_put_contents($keyFile, $hexKey, LOCK_EX)) {
                chmod($keyFile, 0600); // Read only for owner
                
                $this->security->logSecurityEvent('encryption_key_generated', 'high', [
                    'key_file' => $keyFile,
                    'key_length' => strlen($key)
                ], 'New encryption key generated');
                
                return $key;
            } else {
                throw new Exception("Failed to save encryption key");
            }
            
        } catch (Exception $e) {
            error_log("DataEncryption: Key generation error - " . $e->getMessage());
            // Fallback to session-based key (less secure)
            if (!isset($_SESSION['temp_encryption_key'])) {
                $_SESSION['temp_encryption_key'] = bin2hex(random_bytes(32));
            }
            return hex2bin($_SESSION['temp_encryption_key']);
        }
    }
    
    /**
     * Encrypt sensitive data
     */
    public function encrypt($data, $tableName = '', $fieldName = '', $recordId = '') {
        try {
            if (empty($data) || $data === null) {
                return $data;
            }
            
            // Generate random IV
            $ivLength = openssl_cipher_iv_length($this->cipher);
            $iv = random_bytes($ivLength);
            
            // Encrypt data
            $tag = '';
            $encrypted = openssl_encrypt(
                $data,
                $this->cipher,
                $this->encryptionKey,
                OPENSSL_RAW_DATA,
                $iv,
                $tag
            );
            
            if ($encrypted === false) {
                throw new Exception('Encryption failed');
            }
            
            // Combine IV + tag + encrypted data
            $result = base64_encode($iv . $tag . $encrypted);
            
            // Track encrypted field if metadata provided
            if ($tableName && $fieldName && $recordId) {
                $this->trackEncryptedField($tableName, $fieldName, $recordId);
                $this->auditOperation('encrypt', $tableName, $fieldName, $recordId, true);
            }
            
            return $result;
            
        } catch (Exception $e) {
            error_log("DataEncryption: Encrypt error - " . $e->getMessage());
            
            if ($tableName && $fieldName && $recordId) {
                $this->auditOperation('encrypt', $tableName, $fieldName, $recordId, false, $e->getMessage());
            }
            
            return $data; // Return original data if encryption fails
        }
    }
    
    /**
     * Decrypt sensitive data
     */
    public function decrypt($encryptedData, $tableName = '', $fieldName = '', $recordId = '') {
        try {
            if (empty($encryptedData) || $encryptedData === null) {
                return $encryptedData;
            }
            
            // Check if data is actually encrypted (base64 encoded)
            if (!$this->isEncrypted($encryptedData)) {
                return $encryptedData; // Return as-is if not encrypted
            }
            
            $data = base64_decode($encryptedData);
            if ($data === false) {
                throw new Exception('Invalid base64 data');
            }
            
            // Extract IV, tag, and encrypted data
            $ivLength = openssl_cipher_iv_length($this->cipher);
            $tagLength = 16; // GCM tag is always 16 bytes
            
            if (strlen($data) < $ivLength + $tagLength) {
                throw new Exception('Invalid encrypted data length');
            }
            
            $iv = substr($data, 0, $ivLength);
            $tag = substr($data, $ivLength, $tagLength);
            $encrypted = substr($data, $ivLength + $tagLength);
            
            // Decrypt data
            $decrypted = openssl_decrypt(
                $encrypted,
                $this->cipher,
                $this->encryptionKey,
                OPENSSL_RAW_DATA,
                $iv,
                $tag
            );
            
            if ($decrypted === false) {
                throw new Exception('Decryption failed');
            }
            
            // Audit decryption if metadata provided
            if ($tableName && $fieldName && $recordId) {
                $this->auditOperation('decrypt', $tableName, $fieldName, $recordId, true);
            }
            
            return $decrypted;
            
        } catch (Exception $e) {
            error_log("DataEncryption: Decrypt error - " . $e->getMessage());
            
            if ($tableName && $fieldName && $recordId) {
                $this->auditOperation('decrypt', $tableName, $fieldName, $recordId, false, $e->getMessage());
            }
            
            return $encryptedData; // Return encrypted data if decryption fails
        }
    }
    
    /**
     * Check if data appears to be encrypted
     */
    private function isEncrypted($data) {
        // Check if it's valid base64 and has minimum length
        if (base64_decode($data, true) === false) {
            return false;
        }
        
        $decoded = base64_decode($data);
        $minLength = openssl_cipher_iv_length($this->cipher) + 16 + 1; // IV + tag + at least 1 byte data
        
        return strlen($decoded) >= $minLength;
    }
    
    /**
     * Track encrypted field in database
     */
    private function trackEncryptedField($tableName, $fieldName, $recordId) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO encrypted_fields (table_name, field_name, record_id, encryption_method)
                VALUES (?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE 
                updated_at = CURRENT_TIMESTAMP,
                encryption_method = VALUES(encryption_method)
            ");
            $stmt->execute([$tableName, $fieldName, $recordId, $this->cipher]);
        } catch (Exception $e) {
            error_log("DataEncryption: trackEncryptedField error - " . $e->getMessage());
        }
    }
    
    /**
     * Audit encryption/decryption operation
     */
    private function auditOperation($operation, $tableName, $fieldName, $recordId, $success, $errorMessage = null) {
        try {
            $userId = $_SESSION['user_id'] ?? $_SESSION['voter_pk'] ?? null;
            $userType = $_SESSION['user_role'] ?? 'system';
            $ipAddress = $this->security->getClientIP();
            
            $stmt = $this->pdo->prepare("
                INSERT INTO encryption_audit (operation, table_name, field_name, record_id, user_id, user_type, ip_address, success, error_message)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $operation,
                $tableName,
                $fieldName,
                $recordId,
                $userId,
                $userType,
                $ipAddress,
                $success,
                $errorMessage
            ]);
        } catch (Exception $e) {
            error_log("DataEncryption: auditOperation error - " . $e->getMessage());
        }
    }
    
    /**
     * Encrypt voter personal information
     */
    public function encryptVoterData($voterId, $data) {
        $encryptedData = [];
        $sensitiveFields = ['email', 'phone', 'address'];
        
        foreach ($data as $field => $value) {
            if (in_array($field, $sensitiveFields) && !empty($value)) {
                $encryptedData[$field] = $this->encrypt($value, 'voters', $field, $voterId);
            } else {
                $encryptedData[$field] = $value;
            }
        }
        
        return $encryptedData;
    }
    
    /**
     * Decrypt voter personal information
     */
    public function decryptVoterData($voterId, $data) {
        $decryptedData = [];
        $sensitiveFields = ['email', 'phone', 'address'];
        
        foreach ($data as $field => $value) {
            if (in_array($field, $sensitiveFields) && !empty($value)) {
                $decryptedData[$field] = $this->decrypt($value, 'voters', $field, $voterId);
            } else {
                $decryptedData[$field] = $value;
            }
        }
        
        return $decryptedData;
    }
    
    /**
     * Encrypt admin data
     */
    public function encryptAdminData($adminId, $data) {
        $encryptedData = [];
        $sensitiveFields = ['fullname']; // Can add more as needed
        
        foreach ($data as $field => $value) {
            if (in_array($field, $sensitiveFields) && !empty($value)) {
                $encryptedData[$field] = $this->encrypt($value, 'admins', $field, $adminId);
            } else {
                $encryptedData[$field] = $value;
            }
        }
        
        return $encryptedData;
    }
    
    /**
     * Decrypt admin data
     */
    public function decryptAdminData($adminId, $data) {
        $decryptedData = [];
        $sensitiveFields = ['fullname'];
        
        foreach ($data as $field => $value) {
            if (in_array($field, $sensitiveFields) && !empty($value)) {
                $decryptedData[$field] = $this->decrypt($value, 'admins', $field, $adminId);
            } else {
                $decryptedData[$field] = $value;
            }
        }
        
        return $decryptedData;
    }
    
    /**
     * Hash sensitive data for searching (one-way)
     */
    public function hashForSearch($data, $salt = '') {
        if (empty($salt)) {
            $salt = 'smartvote_search_salt_2024';
        }
        
        return hash('sha256', $salt . strtolower(trim($data)));
    }
    
    /**
     * Rotate encryption key (re-encrypt all data with new key)
     */
    public function rotateEncryptionKey() {
        try {
            // Generate new key
            $newKey = random_bytes(32);
            $oldKey = $this->encryptionKey;
            
            // Get all encrypted fields
            $stmt = $this->pdo->query("
                SELECT DISTINCT table_name, field_name, record_id 
                FROM encrypted_fields 
                ORDER BY table_name, record_id
            ");
            $encryptedFields = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $rotatedCount = 0;
            $errorCount = 0;
            
            foreach ($encryptedFields as $field) {
                try {
                    // Get current encrypted data
                    $sql = "SELECT {$field['field_name']} FROM {$field['table_name']} WHERE id = ?";
                    $stmt = $this->pdo->prepare($sql);
                    $stmt->execute([$field['record_id']]);
                    $currentData = $stmt->fetchColumn();
                    
                    if ($currentData) {
                        // Decrypt with old key
                        $this->encryptionKey = $oldKey;
                        $decrypted = $this->decrypt($currentData);
                        
                        // Encrypt with new key
                        $this->encryptionKey = $newKey;
                        $reencrypted = $this->encrypt($decrypted, $field['table_name'], $field['field_name'], $field['record_id']);
                        
                        // Update database
                        $updateSql = "UPDATE {$field['table_name']} SET {$field['field_name']} = ? WHERE id = ?";
                        $updateStmt = $this->pdo->prepare($updateSql);
                        $updateStmt->execute([$reencrypted, $field['record_id']]);
                        
                        $rotatedCount++;
                    }
                } catch (Exception $e) {
                    error_log("DataEncryption: Key rotation error for {$field['table_name']}.{$field['field_name']} - " . $e->getMessage());
                    $errorCount++;
                }
            }
            
            // Save new key
            $keyFile = __DIR__ . '/../.encryption_key';
            file_put_contents($keyFile, bin2hex($newKey), LOCK_EX);
            
            // Update instance key
            $this->encryptionKey = $newKey;
            
            // Audit key rotation
            $this->auditOperation('key_rotation', 'system', 'encryption_key', 'master', true);
            
            $this->security->logSecurityEvent('encryption_key_rotated', 'high', [
                'rotated_fields' => $rotatedCount,
                'errors' => $errorCount
            ], "Encryption key rotated successfully");
            
            return [
                'success' => true,
                'rotated_count' => $rotatedCount,
                'error_count' => $errorCount,
                'message' => "Key rotation completed. $rotatedCount fields re-encrypted."
            ];
            
        } catch (Exception $e) {
            error_log("DataEncryption: rotateEncryptionKey error - " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Key rotation failed: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Get encryption statistics
     */
    public function getStatistics() {
        try {
            $stats = [];
            
            // Count encrypted fields by table
            $stmt = $this->pdo->query("
                SELECT table_name, field_name, COUNT(*) as count
                FROM encrypted_fields 
                GROUP BY table_name, field_name
                ORDER BY table_name, field_name
            ");
            $stats['encrypted_fields'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Encryption/decryption operations in last 24 hours
            $stmt = $this->pdo->query("
                SELECT 
                    operation,
                    success,
                    COUNT(*) as count
                FROM encryption_audit 
                WHERE created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
                GROUP BY operation, success
                ORDER BY operation, success
            ");
            $stats['recent_operations'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Error rate
            $stmt = $this->pdo->query("
                SELECT 
                    (SUM(CASE WHEN success = 0 THEN 1 ELSE 0 END) / COUNT(*)) * 100 as error_rate
                FROM encryption_audit 
                WHERE created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
            ");
            $stats['error_rate'] = round($stmt->fetchColumn(), 2);
            
            return $stats;
            
        } catch (Exception $e) {
            error_log("DataEncryption: getStatistics error - " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Verify encryption system integrity
     */
    public function verifyIntegrity() {
        try {
            $issues = [];
            
            // Check if encryption key file exists and is readable
            $keyFile = __DIR__ . '/../.encryption_key';
            if (!file_exists($keyFile)) {
                $issues[] = 'Encryption key file missing';
            } elseif (!is_readable($keyFile)) {
                $issues[] = 'Encryption key file not readable';
            }
            
            // Test encryption/decryption
            $testData = 'SmartVote encryption test ' . time();
            $encrypted = $this->encrypt($testData);
            $decrypted = $this->decrypt($encrypted);
            
            if ($decrypted !== $testData) {
                $issues[] = 'Encryption/decryption test failed';
            }
            
            // Check for untracked encrypted fields (fields that look encrypted but aren't tracked)
            $potentialIssues = $this->findUntrackedEncryptedFields();
            if (!empty($potentialIssues)) {
                $issues = array_merge($issues, $potentialIssues);
            }
            
            return [
                'healthy' => empty($issues),
                'issues' => $issues,
                'last_check' => date('Y-m-d H:i:s')
            ];
            
        } catch (Exception $e) {
            return [
                'healthy' => false,
                'issues' => ['System error: ' . $e->getMessage()],
                'last_check' => date('Y-m-d H:i:s')
            ];
        }
    }
    
    /**
     * Find potentially encrypted fields that aren't being tracked
     */
    private function findUntrackedEncryptedFields() {
        $issues = [];
        
        try {
            // Check voters table for encrypted-looking data
            $stmt = $this->pdo->query("
                SELECT id, email, phone, address 
                FROM voters 
                WHERE (email REGEXP '^[A-Za-z0-9+/]+=*$' AND LENGTH(email) > 20)
                   OR (phone REGEXP '^[A-Za-z0-9+/]+=*$' AND LENGTH(phone) > 20)
                   OR (address REGEXP '^[A-Za-z0-9+/]+=*$' AND LENGTH(address) > 20)
                LIMIT 10
            ");
            
            $suspiciousVoters = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($suspiciousVoters as $voter) {
                foreach (['email', 'phone', 'address'] as $field) {
                    if (isset($voter[$field]) && $this->isEncrypted($voter[$field])) {
                        // Check if tracked
                        $trackStmt = $this->pdo->prepare("
                            SELECT COUNT(*) FROM encrypted_fields 
                            WHERE table_name = 'voters' AND field_name = ? AND record_id = ?
                        ");
                        $trackStmt->execute([$field, $voter['id']]);
                        
                        if ($trackStmt->fetchColumn() == 0) {
                            $issues[] = "Untracked encrypted field: voters.{$field} (ID: {$voter['id']})";
                        }
                    }
                }
            }
            
        } catch (Exception $e) {
            $issues[] = 'Error checking for untracked fields: ' . $e->getMessage();
        }
        
        return $issues;
    }
}
?>
