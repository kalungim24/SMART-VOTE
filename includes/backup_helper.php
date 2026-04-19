<?php
/**
 * Backup Helper Functions for SmartVote System
 */

class BackupHelper {
    private $pdo;
    private $backup_dir;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->backup_dir = __DIR__ . '/../backups/';
        
        // Create backup directory if it doesn't exist
        if (!file_exists($this->backup_dir)) {
            mkdir($this->backup_dir, 0755, true);
        }
    }
    
    /**
     * Create a full database backup
     */
    public function createFullBackup($created_by = null) {
        try {
            $timestamp = date('Y-m-d_H-i-s');
            $filename = "smartvote_full_backup_{$timestamp}.sql";
            $filepath = $this->backup_dir . $filename;
            
            // Get database connection details
            $config = $this->getDbConfig();
            $host = $config['host'];
            $dbname = $config['dbname'];
            $username = $config['username'];
            $password = $config['password'];
            
            // Detect MySQL path (Windows XAMPP or Linux)
            $mysqlPath = $this->getMysqlPath();
            $mysqldumpCmd = $mysqlPath['mysqldump'];
            
            // Create mysqldump command - handle password properly
            $passwordPart = !empty($password) ? "--password=\"{$password}\"" : '';
            $command = "{$mysqldumpCmd} -u {$username} {$passwordPart} --host={$host} --single-transaction --routines --triggers {$dbname} > \"{$filepath}\" 2>&1";
            
            // Execute backup command
            $output = [];
            $return_var = 0;
            exec($command, $output, $return_var);
            
            // Check if file was created and has content
            if (file_exists($filepath) && filesize($filepath) > 0) {
                $file_size = filesize($filepath);
                
                // Log backup in database
                $this->logBackup('full', $filename, $file_size, 'All tables', 'success', $created_by);
                
                return [
                    'success' => true,
                    'filename' => $filename,
                    'filepath' => $filepath,
                    'size' => $file_size
                ];
            } else {
                $errorMsg = !empty($output) ? implode("\n", $output) : 'Backup command failed - no file created';
                throw new Exception($errorMsg);
            }
            
        } catch (Exception $e) {
            $this->logBackup('full', $filename ?? 'unknown', 0, 'All tables', 'failed', $created_by, $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Create a partial backup of specific tables
     */
    public function createPartialBackup($tables, $created_by = null) {
        try {
            $timestamp = date('Y-m-d_H-i-s');
            $filename = "smartvote_partial_backup_{$timestamp}.sql";
            $filepath = $this->backup_dir . $filename;
            
            // Get database connection details
            $config = $this->getDbConfig();
            $host = $config['host'];
            $dbname = $config['dbname'];
            $username = $config['username'];
            $password = $config['password'];
            
            // Detect MySQL path
            $mysqlPath = $this->getMysqlPath();
            $mysqldumpCmd = $mysqlPath['mysqldump'];
            
            $tables_list = implode(' ', array_map(function($table) {
                return escapeshellarg($table);
            }, $tables));
            
            $passwordPart = !empty($password) ? "--password=\"{$password}\"" : '';
            $command = "{$mysqldumpCmd} -u {$username} {$passwordPart} --host={$host} --single-transaction {$dbname} {$tables_list} > \"{$filepath}\" 2>&1";
            
            $output = [];
            $return_var = 0;
            exec($command, $output, $return_var);
            
            if (file_exists($filepath) && filesize($filepath) > 0) {
                $file_size = filesize($filepath);
                $tables_str = implode(', ', $tables);
                
                $this->logBackup('partial', $filename, $file_size, $tables_str, 'success', $created_by);
                
                return [
                    'success' => true,
                    'filename' => $filename,
                    'filepath' => $filepath,
                    'size' => $file_size
                ];
            } else {
                $errorMsg = !empty($output) ? implode("\n", $output) : 'Partial backup command failed';
                throw new Exception($errorMsg);
            }
            
        } catch (Exception $e) {
            $this->logBackup('partial', $filename ?? 'unknown', 0, implode(', ', $tables), 'failed', $created_by, $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Get list of available backups
     */
    public function getBackupList() {
        $backups = [];
        $files = glob($this->backup_dir . '*.sql');
        
        foreach ($files as $file) {
            $backups[] = [
                'filename' => basename($file),
                'filepath' => $file,
                'size' => filesize($file),
                'created' => date('Y-m-d H:i:s', filemtime($file))
            ];
        }
        
        // Sort by creation time (newest first)
        usort($backups, function($a, $b) {
            return strtotime($b['created']) - strtotime($a['created']);
        });
        
        return $backups;
    }
    
    /**
     * Delete a backup file
     */
    public function deleteBackup($filename) {
        $filepath = $this->backup_dir . $filename;
        if (file_exists($filepath)) {
            return unlink($filepath);
        }
        return false;
    }
    
    /**
     * Restore database from backup
     */
    public function restoreBackup($filename) {
        try {
            $filepath = $this->backup_dir . $filename;
            if (!file_exists($filepath)) {
                throw new Exception('Backup file not found');
            }
            
            // Get database connection details
            $config = $this->getDbConfig();
            $host = $config['host'];
            $dbname = $config['dbname'];
            $username = $config['username'];
            $password = $config['password'];
            
            // Detect MySQL path
            $mysqlPath = $this->getMysqlPath();
            $mysqlCmd = $mysqlPath['mysql'];
            
            // Create mysql restore command
            $passwordPart = !empty($password) ? "--password=\"{$password}\"" : '';
            $command = "{$mysqlCmd} -u {$username} {$passwordPart} --host={$host} {$dbname} < \"{$filepath}\" 2>&1";
            
            $output = [];
            $return_var = 0;
            exec($command, $output, $return_var);
            
            if ($return_var === 0) {
                return [
                    'success' => true,
                    'message' => 'Database restored successfully'
                ];
            } else {
                $errorMsg = !empty($output) ? implode("\n", $output) : 'Restore command failed';
                throw new Exception($errorMsg);
            }
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Log backup activity
     */
    private function logBackup($type, $filename, $size, $tables, $status, $created_by, $error = null) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO backup_logs (backup_type, file_name, file_size, tables_included, status, created_by, error_message) 
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$type, $filename, $size, $tables, $status, $created_by, $error]);
        } catch (Exception $e) {
            // Log error silently
        }
    }
    
    /**
     * Get backup logs from database
     */
    public function getBackupLogs($limit = 50) {
        $stmt = $this->pdo->prepare("
            SELECT bl.*, a.fullname as created_by_name 
            FROM backup_logs bl 
            LEFT JOIN admins a ON bl.created_by = a.id 
            ORDER BY bl.created_at DESC 
            LIMIT ?
        ");
        $stmt->execute([$limit]);
        return $stmt->fetchAll();
    }
    
    /**
     * Get available tables for partial backup
     */
    public function getAvailableTables() {
        $stmt = $this->pdo->query("SHOW TABLES");
        $tables = [];
        while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
            $tables[] = $row[0];
        }
        return $tables;
    }
    
    /**
     * Create a backup with custom name and type
     */
    public function createBackupWithName($backupName, $backupType = 'full') {
        $timestamp = date('Y-m-d_H-i-s');
        $filename = "{$backupName}_{$timestamp}.sql";
        
        if ($backupType === 'full') {
            return $this->createFullBackup();
        } else {
            // For data-only or structure-only, we'll do full backup for now
            // In a more advanced implementation, you could parse mysqldump options
            return $this->createFullBackup();
        }
    }
    
    /**
     * Get database config from connection or config file
     */
    private function getDbConfig() {
        // Try to get config from included file
        if (file_exists(__DIR__ . '/db_config.php')) {
            require_once __DIR__ . '/db_config.php';
            return [
                'host' => $db_host ?? '127.0.0.1',
                'dbname' => $db_name ?? 'smartvote',
                'username' => $db_user ?? 'root',
                'password' => $db_pass ?? ''
            ];
        }
        
        // Fallback to defaults
        return [
            'host' => '127.0.0.1',
            'dbname' => 'smartvote',
            'username' => 'root',
            'password' => ''
        ];
    }
    
    /**
     * Get MySQL/MariaDB binary paths
     */
    private function getMysqlPath() {
        // Windows XAMPP path
        $xamppPath = 'C:\\xampp\\mysql\\bin\\';
        if (file_exists($xamppPath . 'mysqldump.exe')) {
            return [
                'mysqldump' => $xamppPath . 'mysqldump.exe',
                'mysql' => $xamppPath . 'mysql.exe'
            ];
        }
        
        // Try common Linux paths
        $linuxPaths = [
            '/usr/bin/mysqldump',
            '/usr/local/bin/mysqldump',
            '/opt/lampp/bin/mysqldump'
        ];
        
        foreach ($linuxPaths as $path) {
            if (file_exists($path)) {
                return [
                    'mysqldump' => $path,
                    'mysql' => str_replace('mysqldump', 'mysql', $path)
                ];
            }
        }
        
        // Fallback to system PATH
        return [
            'mysqldump' => 'mysqldump',
            'mysql' => 'mysql'
        ];
    }
}

// Global wrapper functions for backward compatibility
if (!function_exists('createBackup')) {
    function createBackup($backupName = null, $backupType = 'full') {
        global $pdo;
        if (!isset($pdo)) {
            throw new Exception('Database connection not available');
        }
        
        $helper = new BackupHelper($pdo);
        $created_by = $_SESSION['user_id'] ?? $_SESSION['admin_id'] ?? null;
        
        // Create backup based on type
        if ($backupType === 'full') {
            $result = $helper->createFullBackup($created_by);
        } elseif ($backupType === 'data') {
            // For data-only backup, we'll do a full backup for now
            // In a more advanced implementation, you could add --no-create-info flag
            $result = $helper->createFullBackup($created_by);
        } elseif ($backupType === 'structure') {
            // For structure-only backup, we'll do a full backup for now
            // In a more advanced implementation, you could add --no-data flag
            $result = $helper->createFullBackup($created_by);
        } else {
            $result = $helper->createFullBackup($created_by);
        }
        
        // Rename file if custom name provided
        if ($result['success'] && !empty($backupName)) {
            $newFilename = $backupName . '.sql';
            $newFilepath = dirname($result['filepath']) . '/' . $newFilename;
            if (rename($result['filepath'], $newFilepath)) {
                $result['filepath'] = $newFilepath;
                $result['filename'] = $newFilename;
            }
        }
        
        if ($result['success']) {
            return $result['filepath'];
        } else {
            throw new Exception($result['error'] ?? 'Backup creation failed');
        }
    }
}

if (!function_exists('restoreBackup')) {
    function restoreBackup($filepath) {
        global $pdo;
        if (!isset($pdo)) {
            throw new Exception('Database connection not available');
        }
        
        $helper = new BackupHelper($pdo);
        
        // Extract filename from path
        if (is_file($filepath)) {
            $filename = basename($filepath);
        } else {
            // Assume it's already a filename
            $filename = $filepath;
        }
        
        $result = $helper->restoreBackup($filename);
        
        return $result['success'];
    }
}
