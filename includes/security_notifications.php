<?php
/**
 * SmartVote Security Notification System
 * Sends email alerts for security events and threats
 */

class SecurityNotifications {
    private $pdo;
    private $security;
    private $adminEmails = [];
    private $alertThresholds = [
        'failed_logins' => 5,
        'blocked_ips' => 3,
        'security_events' => 10
    ];
    
    public function __construct($pdo, $security) {
        $this->pdo = $pdo;
        $this->security = $security;
        $this->initNotificationSettings();
    }
    
    /**
     * Initialize notification settings and tables
     */
    private function initNotificationSettings() {
        try {
            // Get admin emails
            $stmt = $this->pdo->query("SELECT username, fullname FROM admins WHERE id > 0");
            $admins = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($admins as $admin) {
                // Assume admin emails follow pattern: username@domain.com
                // In production, add email field to admins table
                $this->adminEmails[] = [
                    'email' => $admin['username'] . '@smartvote.system',
                    'name' => $admin['fullname']
                ];
            }
            
            // Create notification logs table
            $this->pdo->exec("
                CREATE TABLE IF NOT EXISTS security_notifications (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    alert_type VARCHAR(50) NOT NULL,
                    severity ENUM('low', 'medium', 'high', 'critical') NOT NULL,
                    subject VARCHAR(255) NOT NULL,
                    message TEXT NOT NULL,
                    recipients JSON NOT NULL,
                    sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    event_data JSON NULL,
                    INDEX idx_type_severity (alert_type, severity),
                    INDEX idx_sent_at (sent_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
            
            // Create notification settings table
            $this->pdo->exec("
                CREATE TABLE IF NOT EXISTS notification_settings (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    setting_name VARCHAR(100) NOT NULL UNIQUE,
                    setting_value TEXT NOT NULL,
                    description TEXT,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
            
            // Initialize default settings
            $this->initDefaultSettings();
            
        } catch (Exception $e) {
            error_log("SecurityNotifications: Failed to initialize - " . $e->getMessage());
        }
    }
    
    /**
     * Initialize default notification settings
     */
    private function initDefaultSettings() {
        $defaultSettings = [
            'failed_login_threshold' => ['value' => '5', 'description' => 'Failed login attempts before alert'],
            'blocked_ip_threshold' => ['value' => '3', 'description' => 'Blocked IPs before alert'],
            'security_event_threshold' => ['value' => '10', 'description' => 'Security events before alert'],
            'notification_cooldown' => ['value' => '300', 'description' => 'Seconds between similar alerts'],
            'admin_emails' => ['value' => json_encode($this->adminEmails), 'description' => 'Admin notification recipients'],
            'smtp_enabled' => ['value' => 'false', 'description' => 'Enable SMTP email sending'],
            'smtp_host' => ['value' => '', 'description' => 'SMTP server host'],
            'smtp_port' => ['value' => '587', 'description' => 'SMTP server port'],
            'smtp_username' => ['value' => '', 'description' => 'SMTP authentication username'],
            'smtp_password' => ['value' => '', 'description' => 'SMTP authentication password']
        ];
        
        foreach ($defaultSettings as $name => $config) {
            $stmt = $this->pdo->prepare("
                INSERT IGNORE INTO notification_settings (setting_name, setting_value, description) 
                VALUES (?, ?, ?)
            ");
            $stmt->execute([$name, $config['value'], $config['description']]);
        }
    }
    
    /**
     * Send security alert notification
     */
    public function sendSecurityAlert($alertType, $severity, $subject, $message, $eventData = []) {
        try {
            // Check if we should send this alert (cooldown period)
            if ($this->isInCooldown($alertType, $severity)) {
                return ['success' => false, 'reason' => 'Alert in cooldown period'];
            }
            
            // Get recipients
            $recipients = $this->getAlertRecipients($severity);
            
            if (empty($recipients)) {
                return ['success' => false, 'reason' => 'No recipients configured'];
            }
            
            // Create email content
            $emailContent = $this->buildEmailContent($alertType, $severity, $subject, $message, $eventData);
            
            // Send emails
            $sentCount = 0;
            foreach ($recipients as $recipient) {
                if ($this->sendEmail($recipient['email'], $recipient['name'], $emailContent['subject'], $emailContent['body'])) {
                    $sentCount++;
                }
            }
            
            // Log notification
            $this->logNotification($alertType, $severity, $emailContent['subject'], $emailContent['body'], $recipients, $eventData);
            
            return [
                'success' => $sentCount > 0,
                'sent_count' => $sentCount,
                'total_recipients' => count($recipients)
            ];
            
        } catch (Exception $e) {
            error_log("SecurityNotifications: sendSecurityAlert error - " . $e->getMessage());
            return ['success' => false, 'reason' => 'Failed to send alert'];
        }
    }
    
    /**
     * Check for security events and send alerts if thresholds exceeded
     */
    public function checkAndAlertThresholds() {
        try {
            $recentTime = date('Y-m-d H:i:s', time() - 3600); // Last hour
            
            // Check failed login threshold
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) as count 
                FROM login_attempts 
                WHERE success = FALSE AND attempted_at > ?
            ");
            $stmt->execute([$recentTime]);
            $failedLogins = $stmt->fetchColumn();
            
            if ($failedLogins >= $this->alertThresholds['failed_logins']) {
                $this->sendSecurityAlert(
                    'failed_login_threshold',
                    'medium',
                    'High Number of Failed Login Attempts',
                    "There have been $failedLogins failed login attempts in the last hour, which exceeds the threshold of {$this->alertThresholds['failed_logins']}.",
                    ['failed_login_count' => $failedLogins, 'time_period' => '1 hour']
                );
            }
            
            // Check blocked IP threshold
            $stmt = $this->pdo->prepare("
                SELECT COUNT(DISTINCT ip_address) as count 
                FROM ip_access_logs 
                WHERE status = 'blocked' AND created_at > ?
            ");
            $stmt->execute([$recentTime]);
            $blockedIPs = $stmt->fetchColumn();
            
            if ($blockedIPs >= $this->alertThresholds['blocked_ips']) {
                $this->sendSecurityAlert(
                    'blocked_ip_threshold',
                    'high',
                    'Multiple IP Addresses Blocked',
                    "$blockedIPs different IP addresses have been blocked in the last hour, indicating possible coordinated attack.",
                    ['blocked_ip_count' => $blockedIPs, 'time_period' => '1 hour']
                );
            }
            
            // Check security events threshold
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) as count 
                FROM security_events 
                WHERE severity IN ('high', 'critical') AND created_at > ?
            ");
            $stmt->execute([$recentTime]);
            $highSeverityEvents = $stmt->fetchColumn();
            
            if ($highSeverityEvents >= $this->alertThresholds['security_events']) {
                $this->sendSecurityAlert(
                    'security_event_threshold',
                    'critical',
                    'High Number of Critical Security Events',
                    "There have been $highSeverityEvents high/critical severity security events in the last hour.",
                    ['event_count' => $highSeverityEvents, 'time_period' => '1 hour']
                );
            }
            
        } catch (Exception $e) {
            error_log("SecurityNotifications: checkAndAlertThresholds error - " . $e->getMessage());
        }
    }
    
    /**
     * Send immediate critical alert
     */
    public function sendCriticalAlert($title, $description, $eventData = []) {
        return $this->sendSecurityAlert(
            'critical_security_incident',
            'critical',
            "CRITICAL SECURITY ALERT: $title",
            $description,
            $eventData
        );
    }
    
    /**
     * Send brute force attack alert
     */
    public function sendBruteForceAlert($ipAddress, $attempts, $userType) {
        $subject = "Brute Force Attack Detected";
        $message = "
        A brute force attack has been detected and blocked:
        
        • IP Address: $ipAddress
        • Failed Attempts: $attempts
        • Target: $userType login
        • Time: " . date('Y-m-d H:i:s') . "
        
        The IP address has been automatically locked out.
        ";
        
        return $this->sendSecurityAlert(
            'brute_force_attack',
            'high',
            $subject,
            $message,
            ['ip_address' => $ipAddress, 'attempts' => $attempts, 'user_type' => $userType]
        );
    }
    
    /**
     * Send successful login from new location alert
     */
    public function sendNewLocationAlert($username, $userType, $ipAddress, $location) {
        $subject = "Login from New Location";
        $message = "
        A successful login was detected from a new location:
        
        • User: $username ($userType)
        • IP Address: $ipAddress
        • Location: $location
        • Time: " . date('Y-m-d H:i:s') . "
        
        If this was not authorized, please take immediate action.
        ";
        
        return $this->sendSecurityAlert(
            'new_location_login',
            'medium',
            $subject,
            $message,
            ['username' => $username, 'user_type' => $userType, 'ip_address' => $ipAddress, 'location' => $location]
        );
    }
    
    /**
     * Check if alert type is in cooldown period
     */
    private function isInCooldown($alertType, $severity) {
        try {
            $cooldownSeconds = $this->getSetting('notification_cooldown', 300);
            $cutoffTime = date('Y-m-d H:i:s', time() - $cooldownSeconds);
            
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) 
                FROM security_notifications 
                WHERE alert_type = ? AND severity = ? AND sent_at > ?
            ");
            $stmt->execute([$alertType, $severity, $cutoffTime]);
            
            return $stmt->fetchColumn() > 0;
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Get alert recipients based on severity
     */
    private function getAlertRecipients($severity) {
        try {
            $adminEmailsSetting = $this->getSetting('admin_emails', '[]');
            $adminEmails = json_decode($adminEmailsSetting, true);
            
            // For critical alerts, include all admins
            // For other alerts, might filter based on role or preference
            return $adminEmails;
            
        } catch (Exception $e) {
            return $this->adminEmails; // Fallback to default
        }
    }
    
    /**
     * Build email content
     */
    private function buildEmailContent($alertType, $severity, $subject, $message, $eventData) {
        $severityColors = [
            'low' => '#10B981',
            'medium' => '#F59E0B', 
            'high' => '#EF4444',
            'critical' => '#DC2626'
        ];
        
        $color = $severityColors[$severity] ?? '#6B7280';
        
        $emailSubject = "[SmartVote Security] $subject";
        
        $emailBody = "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <title>SmartVote Security Alert</title>
        </head>
        <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
            <div style='max-width: 600px; margin: 0 auto; padding: 20px;'>
                <div style='background: linear-gradient(135deg, $color 0%, " . $this->darkenColor($color) . " 100%); color: white; padding: 20px; border-radius: 8px 8px 0 0;'>
                    <h1 style='margin: 0; font-size: 24px;'>🛡️ SmartVote Security Alert</h1>
                    <p style='margin: 5px 0 0 0; opacity: 0.9;'>Severity: " . strtoupper($severity) . "</p>
                </div>
                
                <div style='background: #f8f9fa; padding: 20px; border: 1px solid #dee2e6;'>
                    <h2 style='color: $color; margin-top: 0;'>$subject</h2>
                    <div style='background: white; padding: 15px; border-radius: 6px; border-left: 4px solid $color;'>
                        " . nl2br(htmlspecialchars($message)) . "
                    </div>
                </div>
                
                <div style='background: #fff; padding: 20px; border: 1px solid #dee2e6; border-top: 0;'>
                    <h3>Event Details:</h3>
                    <table style='width: 100%; border-collapse: collapse;'>
                        <tr>
                            <td style='padding: 8px; border-bottom: 1px solid #eee; font-weight: bold;'>Alert Type:</td>
                            <td style='padding: 8px; border-bottom: 1px solid #eee;'>$alertType</td>
                        </tr>
                        <tr>
                            <td style='padding: 8px; border-bottom: 1px solid #eee; font-weight: bold;'>Time:</td>
                            <td style='padding: 8px; border-bottom: 1px solid #eee;'>" . date('Y-m-d H:i:s') . "</td>
                        </tr>
                        <tr>
                            <td style='padding: 8px; border-bottom: 1px solid #eee; font-weight: bold;'>System:</td>
                            <td style='padding: 8px; border-bottom: 1px solid #eee;'>SmartVote Digital Voting Platform</td>
                        </tr>";
        
        foreach ($eventData as $key => $value) {
            $emailBody .= "
                        <tr>
                            <td style='padding: 8px; border-bottom: 1px solid #eee; font-weight: bold;'>" . ucfirst(str_replace('_', ' ', $key)) . ":</td>
                            <td style='padding: 8px; border-bottom: 1px solid #eee;'>$value</td>
                        </tr>";
        }
        
        $emailBody .= "
                    </table>
                </div>
                
                <div style='background: #f8f9fa; padding: 15px; border-radius: 0 0 8px 8px; text-align: center; font-size: 12px; color: #6c757d;'>
                    <p>This is an automated security alert from SmartVote system.</p>
                    <p>Please review the Security Dashboard for more details.</p>
                </div>
            </div>
        </body>
        </html>";
        
        return [
            'subject' => $emailSubject,
            'body' => $emailBody
        ];
    }
    
    /**
     * Send email
     */
    private function sendEmail($to, $toName, $subject, $body) {
        try {
            $headers = [
                'MIME-Version: 1.0',
                'Content-type: text/html; charset=UTF-8',
                'From: SmartVote Security <security@smartvote.system>',
                'Reply-To: noreply@smartvote.system',
                'X-Mailer: PHP/' . phpversion(),
                'X-Priority: 1',
                'Importance: High'
            ];
            
            // In production, use PHPMailer or similar for better email delivery
            return mail($to, $subject, $body, implode("\r\n", $headers));
            
        } catch (Exception $e) {
            error_log("SecurityNotifications: sendEmail error - " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Log notification
     */
    private function logNotification($alertType, $severity, $subject, $message, $recipients, $eventData) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO security_notifications (alert_type, severity, subject, message, recipients, event_data)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $alertType,
                $severity, 
                $subject,
                $message,
                json_encode($recipients),
                json_encode($eventData)
            ]);
        } catch (Exception $e) {
            error_log("SecurityNotifications: logNotification error - " . $e->getMessage());
        }
    }
    
    /**
     * Get notification setting
     */
    private function getSetting($name, $default = null) {
        try {
            $stmt = $this->pdo->prepare("SELECT setting_value FROM notification_settings WHERE setting_name = ?");
            $stmt->execute([$name]);
            $result = $stmt->fetchColumn();
            
            return $result !== false ? $result : $default;
        } catch (Exception $e) {
            return $default;
        }
    }
    
    /**
     * Darken a hex color
     */
    private function darkenColor($hex, $percent = 20) {
        $hex = str_replace('#', '', $hex);
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));
        
        $r = max(0, $r - ($r * $percent / 100));
        $g = max(0, $g - ($g * $percent / 100));
        $b = max(0, $b - ($b * $percent / 100));
        
        return '#' . sprintf('%02x%02x%02x', $r, $g, $b);
    }
    
    /**
     * Get notification statistics
     */
    public function getStatistics($days = 7) {
        try {
            $cutoffTime = date('Y-m-d H:i:s', time() - ($days * 86400));
            
            $stmt = $this->pdo->prepare("
                SELECT 
                    alert_type,
                    severity,
                    COUNT(*) as count,
                    MAX(sent_at) as last_sent
                FROM security_notifications 
                WHERE sent_at > ?
                GROUP BY alert_type, severity
                ORDER BY count DESC
            ");
            $stmt->execute([$cutoffTime]);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("SecurityNotifications: getStatistics error - " . $e->getMessage());
            return [];
        }
    }
}
?>
