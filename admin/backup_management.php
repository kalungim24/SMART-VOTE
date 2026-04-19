<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/backup_helper.php';
require_once __DIR__ . '/../includes/activity_logger.php';
require_role('admin');

// Initialize activity logger
$activityLogger = new ActivityLogger($pdo);

$message = '';
$messageType = 'success';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'create_backup') {
        try {
            $backupType = $_POST['backup_type'] ?? 'full';
            $backupName = trim($_POST['backup_name'] ?? '');
            
            if (empty($backupName)) {
                $backupName = 'backup_' . date('Y-m-d_H-i-s');
            }
            
            $backupPath = createBackup($backupName, $backupType);
            
            if ($backupPath) {
                $backupSize = file_exists($backupPath) ? filesize($backupPath) : 0;
                $message = 'Backup created successfully: ' . basename($backupPath);
                $messageType = 'success';
                
                // Log the backup creation activity
                $activityLogger->logBackupCreated(basename($backupPath), $backupSize);
                $activityLogger->log('backup_success', 'backup', 'Database backup completed successfully', [
                    'description' => "Backup '$backupName' of type '$backupType' created successfully",
                    'target_type' => 'backup',
                    'target_name' => basename($backupPath),
                    'status' => 'success',
                    'severity' => 2,
                    'metadata' => [
                        'backup_type' => $backupType,
                        'backup_size' => $backupSize,
                        'backup_path' => basename($backupPath)
                    ]
                ]);
            } else {
                $message = 'Error creating backup.';
                $messageType = 'error';
                
                // Log the backup failure
                $activityLogger->log('backup_failed', 'backup', 'Database backup failed', [
                    'description' => "Failed to create backup '$backupName' of type '$backupType'",
                    'status' => 'failure',
                    'severity' => 3,
                    'metadata' => ['backup_name' => $backupName, 'backup_type' => $backupType]
                ]);
            }
            
        } catch (Exception $e) {
            $message = 'Error creating backup: ' . $e->getMessage();
            $messageType = 'error';
            
            // Log the backup exception
            $activityLogger->log('backup_error', 'backup', 'Backup creation error', [
                'description' => 'Exception occurred while creating backup: ' . $e->getMessage(),
                'status' => 'failure',
                'severity' => 4,
                'metadata' => [
                    'backup_name' => $backupName ?? 'unknown',
                    'backup_type' => $backupType ?? 'unknown',
                    'error_message' => $e->getMessage(),
                    'error_file' => $e->getFile(),
                    'error_line' => $e->getLine()
                ]
            ]);
        }
        
    } elseif ($action === 'restore_backup') {
        $backupFile = $_POST['backup_file'] ?? '';
        
        if (empty($backupFile)) {
            $message = 'Error: Please select a backup file.';
            $messageType = 'error';
        } else {
            try {
                $restorePath = __DIR__ . '/../backups/' . $backupFile;
                
                if (!file_exists($restorePath)) {
                    $message = 'Error: Backup file not found.';
                    $messageType = 'error';
                } else {
                    $result = restoreBackup($restorePath);
                    
                    if ($result) {
                        $message = 'Backup restored successfully.';
                        $messageType = 'success';
                        
                        // Log successful restore
                        $activityLogger->logBackupRestored($backupFile);
                        $activityLogger->log('restore_success', 'backup', 'Database restore completed successfully', [
                            'description' => "Database successfully restored from backup '$backupFile'",
                            'target_type' => 'backup',
                            'target_name' => $backupFile,
                            'status' => 'success',
                            'severity' => 4, // Critical operation
                            'metadata' => [
                                'backup_file' => $backupFile,
                                'restore_path' => basename($restorePath),
                                'restored_at' => date('Y-m-d H:i:s')
                            ]
                        ]);
                    } else {
                        $message = 'Error restoring backup.';
                        $messageType = 'error';
                        
                        // Log restore failure
                        $activityLogger->log('restore_failed', 'backup', 'Database restore failed', [
                            'description' => "Failed to restore database from backup '$backupFile'",
                            'target_type' => 'backup',
                            'target_name' => $backupFile,
                            'status' => 'failure',
                            'severity' => 4,
                            'metadata' => ['backup_file' => $backupFile, 'restore_path' => basename($restorePath)]
                        ]);
                    }
                }
                
            } catch (Exception $e) {
                $message = 'Error restoring backup: ' . $e->getMessage();
                $messageType = 'error';
                
                // Log restore exception
                $activityLogger->log('restore_error', 'backup', 'Backup restore error', [
                    'description' => 'Exception occurred while restoring backup: ' . $e->getMessage(),
                    'target_type' => 'backup',
                    'target_name' => $backupFile,
                    'status' => 'failure',
                    'severity' => 4,
                    'metadata' => [
                        'backup_file' => $backupFile,
                        'error_message' => $e->getMessage(),
                        'error_file' => $e->getFile(),
                        'error_line' => $e->getLine()
                    ]
                ]);
            }
        }
        
    } elseif ($action === 'delete_backup') {
        $backupFile = $_POST['backup_file'] ?? '';
        
        if (empty($backupFile)) {
            $message = 'Error: Please select a backup file.';
            $messageType = 'error';
        } else {
            try {
                $backupPath = __DIR__ . '/../backups/' . $backupFile;
                
                if (file_exists($backupPath)) {
                    $backupSize = filesize($backupPath);
                    unlink($backupPath);
                    $message = 'Backup deleted successfully.';
                    $messageType = 'success';
                    
                    // Log backup deletion
                    $activityLogger->log('delete_backup', 'backup', 'Backup file deleted', [
                        'description' => "Backup file '$backupFile' was permanently deleted",
                        'target_type' => 'backup',
                        'target_name' => $backupFile,
                        'status' => 'success',
                        'severity' => 3,
                        'metadata' => [
                            'backup_file' => $backupFile,
                            'backup_size' => $backupSize,
                            'deleted_at' => date('Y-m-d H:i:s')
                        ]
                    ]);
                } else {
                    $message = 'Error: Backup file not found.';
                    $messageType = 'error';
                    
                    // Log failed deletion attempt
                    $activityLogger->log('delete_backup_failed', 'backup', 'Backup deletion failed - file not found', [
                        'description' => "Attempted to delete non-existent backup '$backupFile'",
                        'target_type' => 'backup',
                        'target_name' => $backupFile,
                        'status' => 'failure',
                        'severity' => 2,
                        'metadata' => ['backup_file' => $backupFile, 'attempted_path' => basename($backupPath)]
                    ]);
                }
                
            } catch (Exception $e) {
                $message = 'Error deleting backup: ' . $e->getMessage();
                $messageType = 'error';
                
                // Log deletion exception
                $activityLogger->log('delete_backup_error', 'backup', 'Backup deletion error', [
                    'description' => 'Exception occurred while deleting backup: ' . $e->getMessage(),
                    'target_type' => 'backup',
                    'target_name' => $backupFile,
                    'status' => 'failure',
                    'severity' => 3,
                    'metadata' => [
                        'backup_file' => $backupFile,
                        'error_message' => $e->getMessage(),
                        'error_file' => $e->getFile(),
                        'error_line' => $e->getLine()
                    ]
                ]);
            }
        }
    }
}

// Get available backups
$backups = [];
$backupDir = __DIR__ . '/../backups/';
if (is_dir($backupDir)) {
    $files = scandir($backupDir);
    foreach ($files as $file) {
        if ($file !== '.' && $file !== '..' && pathinfo($file, PATHINFO_EXTENSION) === 'sql') {
            $filePath = $backupDir . $file;
            $backups[] = [
                'name' => $file,
                'size' => filesize($filePath),
                'created' => filemtime($filePath),
                'path' => $filePath
            ];
        }
    }
    
    // Sort by creation time (newest first)
    usort($backups, function($a, $b) {
        return $b['created'] - $a['created'];
    });
}

// Get real-time activities using the enhanced activity logger
$recentActivities = $activityLogger->getRecentActivities(100);
$activityStats = $activityLogger->getActivityStats('24 hours');

// Function to format file size
function formatFileSize($bytes) {
    $units = ['B', 'KB', 'MB', 'GB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, 2) . ' ' . $units[$pow];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Backup & Logs - SmartVote</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        brand: '#0ea5e9'
                    }
                }
            }
        }
    </script>
    <style>
        @keyframes fade-in {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        .animate-fade-in {
            animation: fade-in 0.3s ease-out;
        }
    </style>
</head>
<body class="bg-gradient-to-br from-slate-50 to-slate-100 min-h-screen">
    <?php include __DIR__ . '/includes/sidebar.php'; ?>
    
    <main class="lg:ml-64 p-4 lg:p-6 pt-16 lg:pt-6 min-h-screen">
            <!-- Header -->
            <div class="mb-8">
                <h1 class="text-3xl font-bold text-slate-800">Backup & Logs</h1>
                <p class="text-slate-600 mt-1">Manage system backups and view activity logs.</p>
            </div>

            <!-- Message Display -->
            <?php if ($message): ?>
                <div class="mb-6 p-4 rounded-lg border flex items-center <?php echo $messageType === 'error' ? 'bg-red-50 border-red-200 text-red-700' : 'bg-emerald-50 border-emerald-200 text-emerald-700'; ?>">
                    <svg class="w-5 h-5 mr-2 <?php echo $messageType === 'error' ? 'text-red-600' : 'text-emerald-600'; ?>" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="<?php echo $messageType === 'error' ? 'M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z' : 'M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z'; ?>" clip-rule="evenodd"></path>
                    </svg>
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                <!-- Backup Management -->
                <div class="space-y-6">
                    <!-- Create Backup -->
                    <div class="bg-white rounded-xl shadow-lg p-6">
                        <div class="flex items-center mb-6">
                            <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center mr-3">
                                <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path>
                                </svg>
                            </div>
                            <h2 class="text-xl font-semibold text-slate-800">Create Backup</h2>
                        </div>

                        <form method="post" class="space-y-4">
                            <input type="hidden" name="action" value="create_backup">
                            
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1">Backup Name</label>
                                <input name="backup_name" 
                                       placeholder="Enter backup name (optional)" 
                                       class="w-full border border-slate-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-brand focus:border-transparent">
                                <p class="text-xs text-slate-500 mt-1">Leave empty for auto-generated name</p>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1">Backup Type</label>
                                <select name="backup_type" class="w-full border border-slate-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-brand focus:border-transparent">
                                    <option value="full">Full Database Backup</option>
                                    <option value="data">Data Only</option>
                                    <option value="structure">Structure Only</option>
                                </select>
                            </div>

                            <div class="flex justify-end">
                                <button type="submit" class="bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700 text-white rounded-lg px-6 py-3 font-semibold shadow-lg hover:shadow-xl transition-all duration-200">
                                    Create Backup
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- Restore Backup -->
                    <div class="bg-white rounded-xl shadow-lg p-6">
                        <div class="flex items-center mb-6">
                            <div class="w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center mr-3">
                                <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M9 19l3 3m0 0l3-3m-3 3V10"></path>
                                </svg>
                            </div>
                            <h2 class="text-xl font-semibold text-slate-800">Restore Backup</h2>
                        </div>

                        <form method="post" class="space-y-4" onsubmit="return confirm('Are you sure you want to restore this backup? This will overwrite all current data.')">
                            <input type="hidden" name="action" value="restore_backup">
                            
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1">Select Backup</label>
                                <select name="backup_file" required class="w-full border border-slate-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-brand focus:border-transparent">
                                    <option value="">Choose a backup file</option>
                                    <?php foreach ($backups as $backup): ?>
                                        <option value="<?php echo htmlspecialchars($backup['name']); ?>">
                                            <?php echo htmlspecialchars($backup['name']); ?> 
                                            (<?php echo formatFileSize($backup['size']); ?>, <?php echo date('M j, Y g:i A', $backup['created']); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="flex justify-end">
                                <button type="submit" class="bg-gradient-to-r from-green-600 to-emerald-600 hover:from-green-700 hover:to-emerald-700 text-white rounded-lg px-6 py-3 font-semibold shadow-lg hover:shadow-xl transition-all duration-200">
                                    Restore Backup
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Backup List & Logs -->
                <div class="space-y-6">
                    <!-- Available Backups -->
                    <div class="bg-white rounded-xl shadow-lg overflow-hidden">
                        <div class="px-6 py-4 border-b border-slate-200">
                            <h2 class="text-xl font-semibold text-slate-800">Available Backups</h2>
                        </div>

                        <div class="overflow-x-auto">
                            <?php if (empty($backups)): ?>
                                <div class="p-6 text-center text-slate-500">
                                    <svg class="w-12 h-12 mx-auto mb-4 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path>
                                    </svg>
                                    <p>No backups available</p>
                                </div>
                            <?php else: ?>
                                <table class="min-w-full divide-y divide-slate-200">
                                    <thead class="bg-slate-50">
                                        <tr>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Name</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Size</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Created</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-slate-200">
                                        <?php foreach ($backups as $backup): ?>
                                            <tr class="hover:bg-slate-50 transition-colors">
                                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-slate-900">
                                                    <?php echo htmlspecialchars($backup['name']); ?>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-600">
                                                    <?php echo formatFileSize($backup['size']); ?>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-600">
                                                    <?php echo date('M j, Y g:i A', $backup['created']); ?>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                                    <div class="flex items-center space-x-2">
                                                        <a href="../backups/<?php echo htmlspecialchars($backup['name']); ?>" 
                                                           download class="inline-flex items-center px-2 py-1 text-xs font-medium text-white bg-blue-600 hover:bg-blue-700 rounded">
                                                            <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                                            </svg>
                                                            Download
                                                        </a>
                                                        
                                                        <form method="post" class="inline" onsubmit="return confirm('Are you sure you want to delete this backup?')">
                                                            <input type="hidden" name="action" value="delete_backup">
                                                            <input type="hidden" name="backup_file" value="<?php echo htmlspecialchars($backup['name']); ?>">
                                                            <button type="submit" class="inline-flex items-center px-2 py-1 text-xs font-medium text-white bg-red-600 hover:bg-red-700 rounded">
                                                                <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                                                </svg>
                                                                Delete
                                                            </button>
                                                        </form>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Real-Time Activity Feed -->
                    <div class="bg-white rounded-xl shadow-lg overflow-hidden">
                        <div class="px-6 py-4 border-b border-slate-200">
                            <div class="flex items-center justify-between">
                                <h2 class="text-xl font-semibold text-slate-800">Real-Time Activities</h2>
                                <div class="flex items-center space-x-2">
                                    <div class="flex items-center">
                                        <div class="w-2 h-2 bg-green-500 rounded-full animate-pulse mr-2"></div>
                                        <span class="text-sm text-slate-600">Live Feed</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Activity Filters -->
                        <div class="px-6 py-3 border-b border-slate-100 bg-slate-50">
                            <div class="flex flex-wrap gap-2">
                                <button class="activity-filter active px-3 py-1 text-xs font-medium rounded-full bg-blue-100 text-blue-800" data-category="all">All</button>
                                <button class="activity-filter px-3 py-1 text-xs font-medium rounded-full bg-gray-100 text-gray-600 hover:bg-gray-200" data-category="backup">Backup</button>
                                <button class="activity-filter px-3 py-1 text-xs font-medium rounded-full bg-gray-100 text-gray-600 hover:bg-gray-200" data-category="authentication">Auth</button>
                                <button class="activity-filter px-3 py-1 text-xs font-medium rounded-full bg-gray-100 text-gray-600 hover:bg-gray-200" data-category="election">Elections</button>
                                <button class="activity-filter px-3 py-1 text-xs font-medium rounded-full bg-gray-100 text-gray-600 hover:bg-gray-200" data-category="voting">Voting</button>
                                <button class="activity-filter px-3 py-1 text-xs font-medium rounded-full bg-gray-100 text-gray-600 hover:bg-gray-200" data-category="security">Security</button>
                            </div>
                        </div>

                        <div class="max-h-96 overflow-y-auto" id="activity-feed">
                            <?php if (empty($recentActivities)): ?>
                                <div class="p-6 text-center text-slate-500">
                                    <svg class="w-12 h-12 mx-auto mb-4 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                    </svg>
                                    <p>No activities recorded yet</p>
                                </div>
                            <?php else: ?>
                                <div class="divide-y divide-slate-200">
                                    <?php 
                                    function getActivityIcon($category) {
                                        switch ($category) {
                                            case 'backup': return '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path>';
                                            case 'authentication': return '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>';
                                            case 'election': return '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>';
                                            case 'voting': return '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>';
                                            case 'security': return '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>';
                                            default: return '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>';
                                        }
                                    }
                                    
                                    function getStatusColor($status) {
                                        switch ($status) {
                                            case 'success': return 'text-green-600 bg-green-100';
                                            case 'failure': return 'text-red-600 bg-red-100';
                                            case 'warning': return 'text-yellow-600 bg-yellow-100';
                                            default: return 'text-blue-600 bg-blue-100';
                                        }
                                    }
                                    
                                    function getSeverityBadge($severity) {
                                        switch ($severity) {
                                            case 4: return '<span class="inline-flex items-center px-1.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">Critical</span>';
                                            case 3: return '<span class="inline-flex items-center px-1.5 py-0.5 rounded-full text-xs font-medium bg-orange-100 text-orange-800">High</span>';
                                            case 2: return '<span class="inline-flex items-center px-1.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">Medium</span>';
                                            default: return '<span class="inline-flex items-center px-1.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">Low</span>';
                                        }
                                    }
                                    ?>
                                    
                                    <?php foreach ($recentActivities as $activity): ?>
                                        <div class="activity-item px-6 py-4 hover:bg-slate-50 transition-colors" data-category="<?php echo $activity['activity_category']; ?>">
                                            <div class="flex items-start space-x-3">
                                                <div class="flex-shrink-0 w-8 h-8 rounded-lg flex items-center justify-center <?php echo getStatusColor($activity['status']); ?>">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <?php echo getActivityIcon($activity['activity_category']); ?>
                                                    </svg>
                                                </div>
                                                
                                                <div class="flex-1 min-w-0">
                                            <div class="flex items-center justify-between">
                                                        <div class="flex items-center space-x-2">
                                                            <h4 class="text-sm font-medium text-slate-900"><?php echo htmlspecialchars($activity['action']); ?></h4>
                                                            <?php echo getSeverityBadge($activity['severity']); ?>
                                                        </div>
                                                        <div class="flex items-center space-x-2">
                                                            <span class="text-xs text-slate-500"><?php echo $activity['user_type']; ?>: <?php echo htmlspecialchars($activity['username']); ?></span>
                                                            <span class="text-xs text-slate-400">
                                                                <?php echo date('M j, g:i A', strtotime($activity['created_at'])); ?>
                                                            </span>
                                                        </div>
                                                    </div>
                                                    
                                                    <?php if (!empty($activity['description'])): ?>
                                                        <p class="text-sm text-slate-600 mt-1"><?php echo htmlspecialchars($activity['description']); ?></p>
                                                    <?php endif; ?>
                                                    
                                                    <?php if (!empty($activity['target_name'])): ?>
                                                        <div class="flex items-center mt-2 space-x-4">
                                                            <span class="text-xs text-slate-500">
                                                                <span class="font-medium">Target:</span> <?php echo htmlspecialchars($activity['target_type']); ?>
                                                            </span>
                                                            <span class="text-xs text-slate-600 font-medium"><?php echo htmlspecialchars($activity['target_name']); ?></span>
                                                        </div>
                                                    <?php endif; ?>
                                                    
                                                    <?php if (!empty($activity['ip_address'])): ?>
                                                        <div class="text-xs text-slate-400 mt-1">
                                                            IP: <?php echo htmlspecialchars($activity['ip_address']); ?>
                                                </div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Activity Statistics -->
                        <?php if (!empty($activityStats)): ?>
                        <div class="px-6 py-4 border-t border-slate-200 bg-slate-50">
                            <h3 class="text-sm font-medium text-slate-700 mb-3">Last 24 Hours Statistics</h3>
                            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                                <?php foreach ($activityStats as $stat): ?>
                                    <div class="text-center">
                                        <div class="text-lg font-semibold <?php echo getStatusColor($stat['status']); ?> rounded-lg py-1">
                                            <?php echo $stat['count']; ?>
                                        </div>
                                        <div class="text-xs text-slate-500 mt-1">
                                            <?php echo ucfirst($stat['activity_category']); ?><br>
                                            <span class="<?php echo $stat['status'] === 'success' ? 'text-green-600' : ($stat['status'] === 'failure' ? 'text-red-600' : 'text-yellow-600'); ?>">
                                                <?php echo ucfirst($stat['status']); ?>
                                            </span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Quick Stats -->
            <div class="mt-8 grid grid-cols-1 md:grid-cols-4 gap-4">
                <div class="bg-white rounded-lg shadow p-4">
                    <div class="flex items-center">
                        <div class="w-8 h-8 bg-blue-100 rounded-lg flex items-center justify-center mr-3">
                            <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path>
                            </svg>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-slate-600">Total Backups</p>
                            <p class="text-2xl font-bold text-slate-900"><?php echo count($backups); ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-lg shadow p-4">
                    <div class="flex items-center">
                        <div class="w-8 h-8 bg-green-100 rounded-lg flex items-center justify-center mr-3">
                            <svg class="w-4 h-4 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                            </svg>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-slate-600">Total Activities</p>
                            <p class="text-2xl font-bold text-slate-900"><?php echo count($recentActivities); ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-lg shadow p-4">
                    <div class="flex items-center">
                        <div class="w-8 h-8 bg-purple-100 rounded-lg flex items-center justify-center mr-3">
                            <svg class="w-4 h-4 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-slate-600">Last Backup</p>
                            <p class="text-sm font-bold text-slate-900">
                                <?php echo !empty($backups) ? date('M j, Y', $backups[0]['created']) : 'Never'; ?>
                            </p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-lg shadow p-4">
                    <div class="flex items-center">
                        <div class="w-8 h-8 bg-orange-100 rounded-lg flex items-center justify-center mr-3">
                            <svg class="w-4 h-4 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4"></path>
                            </svg>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-slate-600">Total Size</p>
                            <p class="text-sm font-bold text-slate-900">
                                <?php 
                                $totalSize = array_sum(array_column($backups, 'size'));
                                echo formatFileSize($totalSize);
                                ?>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
    
    <script>
        // Real-Time Activity Management
        document.addEventListener('DOMContentLoaded', function() {
            let currentCategory = 'all';
            let lastActivityCount = <?php echo count($recentActivities); ?>;
            let refreshInterval;
            
            // Activity Filter Functionality
            const filterButtons = document.querySelectorAll('.activity-filter');
            
            filterButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const category = this.getAttribute('data-category');
                    currentCategory = category;
                    
                    // Update button states
                    filterButtons.forEach(btn => {
                        btn.classList.remove('active', 'bg-blue-100', 'text-blue-800');
                        btn.classList.add('bg-gray-100', 'text-gray-600');
                    });
                    this.classList.remove('bg-gray-100', 'text-gray-600');
                    this.classList.add('active', 'bg-blue-100', 'text-blue-800');
                    
                    // Fetch filtered activities
                    fetchActivities(category);
                });
            });
            
            // Fetch activities from AJAX endpoint
            function fetchActivities(category = 'all', showNewIndicator = true) {
                const url = `ajax_activities.php?action=get_recent_activities&limit=100&category=${category}&t=${Date.now()}`;
                
                fetch(url)
                    .then(response => response.json())
                    .then(data => {
                        if (data.success && data.data) {
                            const activityFeed = document.getElementById('activity-feed');
                            const newCount = data.count;
                            
                            // Check if there are new activities
                            if (showNewIndicator && newCount > lastActivityCount && lastActivityCount > 0) {
                                showNewActivitiesIndicator(newCount - lastActivityCount);
                            }
                            lastActivityCount = newCount;
                            
                            // Update activity feed
                            if (data.data.length === 0) {
                                activityFeed.innerHTML = `
                                    <div class="p-6 text-center text-slate-500">
                                        <svg class="w-12 h-12 mx-auto mb-4 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                        </svg>
                                        <p>No activities recorded yet</p>
                                    </div>
                                `;
                            } else {
                                activityFeed.innerHTML = renderActivities(data.data);
                            }
                            
                            // Update activity count in stats
                            updateActivityStats();
                        }
                    })
                    .catch(error => {
                        console.error('Error fetching activities:', error);
                    });
            }
            
            // Render activities HTML
            function renderActivities(activities) {
                const activitiesHtml = activities.map(activity => {
                    const statusColor = getStatusColor(activity.status);
                    const severityBadge = getSeverityBadge(activity.severity);
                    const activityIcon = getActivityIcon(activity.activity_category);
                    const timeAgo = formatTimeAgo(activity.created_at);
                    
                    return `
                        <div class="activity-item px-6 py-4 hover:bg-slate-50 transition-colors border-l-2 border-${statusColor.split('-')[1]}-200" 
                             data-category="${activity.activity_category}">
                            <div class="flex items-start space-x-3">
                                <div class="flex-shrink-0 w-8 h-8 rounded-lg flex items-center justify-center ${statusColor}">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        ${activityIcon}
                                    </svg>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center justify-between">
                                        <div class="flex items-center space-x-2">
                                            <h4 class="text-sm font-medium text-slate-900">${escapeHtml(activity.action)}</h4>
                                            ${severityBadge}
                                        </div>
                                        <span class="text-xs text-slate-400">${timeAgo}</span>
                                    </div>
                                    ${activity.description ? `<p class="text-sm text-slate-600 mt-1">${escapeHtml(activity.description)}</p>` : ''}
                                    <div class="flex items-center mt-2 space-x-4 text-xs text-slate-500">
                                        <span>${escapeHtml(activity.user_type || 'system')}: ${escapeHtml(activity.username || 'anonymous')}</span>
                                        ${activity.target_name ? `<span>Target: ${escapeHtml(activity.target_name)}</span>` : ''}
                                        ${activity.ip_address && activity.ip_address !== 'unknown' ? `<span>IP: ${escapeHtml(activity.ip_address)}</span>` : ''}
                                    </div>
                                </div>
                            </div>
                        </div>
                    `;
                }).join('');
                
                return `<div class="divide-y divide-slate-200">${activitiesHtml}</div>`;
            }
            
            // Helper functions
            function getStatusColor(status) {
                switch (status) {
                    case 'success': return 'text-green-600 bg-green-100';
                    case 'failure': return 'text-red-600 bg-red-100';
                    case 'warning': return 'text-yellow-600 bg-yellow-100';
                    default: return 'text-blue-600 bg-blue-100';
                }
            }
            
            function getSeverityBadge(severity) {
                const level = parseInt(severity) || 1;
                if (level >= 4) {
                    return '<span class="inline-flex items-center px-1.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">Critical</span>';
                } else if (level === 3) {
                    return '<span class="inline-flex items-center px-1.5 py-0.5 rounded-full text-xs font-medium bg-orange-100 text-orange-800">High</span>';
                } else if (level === 2) {
                    return '<span class="inline-flex items-center px-1.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">Medium</span>';
                } else {
                    return '<span class="inline-flex items-center px-1.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">Low</span>';
                }
            }
            
            function getActivityIcon(category) {
                const icons = {
                    'backup': '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path>',
                    'authentication': '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>',
                    'election': '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>',
                    'voting': '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>',
                    'security': '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>'
                };
                return icons[category] || '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>';
            }
            
            function formatTimeAgo(dateString) {
                const date = new Date(dateString);
                const now = new Date();
                const diffMs = now - date;
                const diffMins = Math.floor(diffMs / 60000);
                const diffHours = Math.floor(diffMs / 3600000);
                const diffDays = Math.floor(diffMs / 86400000);
                
                if (diffMins < 1) return 'Just now';
                if (diffMins < 60) return `${diffMins}m ago`;
                if (diffHours < 24) return `${diffHours}h ago`;
                if (diffDays < 7) return `${diffDays}d ago`;
                return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: date.getFullYear() !== now.getFullYear() ? 'numeric' : undefined });
            }
            
            function escapeHtml(text) {
                const div = document.createElement('div');
                div.textContent = text;
                return div.innerHTML;
            }
            
            function showNewActivitiesIndicator(count) {
                const liveIndicator = document.querySelector('.animate-pulse');
                if (liveIndicator) {
                    liveIndicator.classList.add('bg-green-400');
                    const notification = document.createElement('div');
                    notification.className = 'fixed top-20 right-4 bg-green-100 border border-green-200 text-green-700 px-4 py-2 rounded-lg shadow-lg z-50 animate-fade-in';
                    notification.innerHTML = `<div class="flex items-center"><span class="font-semibold">${count} new activit${count === 1 ? 'y' : 'ies'}</span></div>`;
                    document.body.appendChild(notification);
                    setTimeout(() => {
                        notification.remove();
                        if (liveIndicator) liveIndicator.classList.remove('bg-green-400');
                    }, 3000);
                }
            }
            
            function updateActivityStats() {
                fetch('ajax_activities.php?action=get_activity_stats&timeframe=24 hours&t=' + Date.now())
                    .then(response => response.json())
                    .then(data => {
                        if (data.success && data.data) {
                            // Update stats display if needed
                            const totalActivities = data.data.reduce((sum, stat) => sum + parseInt(stat.count), 0);
                            // You can update the stats section here if needed
                        }
                    })
                    .catch(error => console.error('Error fetching stats:', error));
            }
            
            // Auto-refresh activity feed every 10 seconds
            function startAutoRefresh() {
                refreshInterval = setInterval(function() {
                    fetchActivities(currentCategory, true);
                }, 10000); // Refresh every 10 seconds
            }
            
            // Start auto-refresh
            startAutoRefresh();
            
            // Stop auto-refresh when page becomes inactive
            document.addEventListener('visibilitychange', function() {
                if (document.hidden) {
                    clearInterval(refreshInterval);
                } else {
                    clearInterval(refreshInterval);
                    startAutoRefresh();
                    // Fetch immediately when page becomes visible
                    fetchActivities(currentCategory, false);
                }
            });
            
            // Initial fetch on load
            fetchActivities(currentCategory, false);
        });
        
        // Backup Management Enhancements
        function confirmAction(action, itemName) {
            const actions = {
                'restore': `Are you sure you want to restore from backup "${itemName}"? This will overwrite all current data and cannot be undone.`,
                'delete': `Are you sure you want to permanently delete backup "${itemName}"? This action cannot be undone.`,
                'create': 'Create a new backup of the current database?'
            };
            
            return confirm(actions[action] || 'Are you sure you want to perform this action?');
        }
        
        // Real-time status updates
        function showProcessingStatus(message) {
            const statusDiv = document.createElement('div');
            statusDiv.className = 'fixed top-4 right-4 bg-blue-100 border border-blue-200 text-blue-700 px-4 py-2 rounded-lg shadow-lg z-50';
            statusDiv.innerHTML = `
                <div class="flex items-center">
                    <svg class="animate-spin -ml-1 mr-3 h-4 w-4 text-blue-600" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    ${message}
                </div>
            `;
            document.body.appendChild(statusDiv);
            
            // Remove after 5 seconds
            setTimeout(() => {
                statusDiv.remove();
            }, 5000);
        }
        
        // Enhanced form submission
        document.querySelectorAll('form').forEach(form => {
            form.addEventListener('submit', function(e) {
                const action = form.querySelector('input[name="action"]');
                if (action) {
                    const actionValue = action.value;
                    const messages = {
                        'create_backup': 'Creating database backup...',
                        'restore_backup': 'Restoring database from backup...',
                        'delete_backup': 'Deleting backup file...'
                    };
                    
                    if (messages[actionValue]) {
                        showProcessingStatus(messages[actionValue]);
                    }
                }
            });
        });
    </script>
</body>
</html>