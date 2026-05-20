<?php

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/security_manager.php';
require_role('admin');



$message = '';
$messageType = 'success';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_system') {
        $systemName = trim($_POST['system_name'] ?? '');
        $systemDescription = trim($_POST['system_description'] ?? '');
        $adminEmail = trim($_POST['admin_email'] ?? '');
        $timezone = trim($_POST['timezone'] ?? '');
        $maxVotesPerElection = (int)($_POST['max_votes_per_election'] ?? 1);
        
        try {
            // Update system settings
            $systemName = $systemName !== '' ? $systemName : 'SmartLonda';
            set_system_name($pdo, $systemName);
            update_system_setting($pdo, 'system_description', $systemDescription);
            update_system_setting($pdo, 'admin_email', $adminEmail);
            update_system_setting($pdo, 'timezone', $timezone);
            update_system_setting($pdo, 'max_votes_per_election', $maxVotesPerElection);
            
            $message = 'System settings updated successfully.';
            $messageType = 'success';
            
        } catch (Exception $e) {
            $message = 'Error updating settings: ' . $e->getMessage();
            $messageType = 'error';
        }
        
    } elseif ($action === 'update_password') {
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        
        if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
            $message = 'Error: Please fill in all password fields.';
            $messageType = 'error';
        } elseif ($newPassword !== $confirmPassword) {
            $message = 'Error: New passwords do not match.';
            $messageType = 'error';
        } elseif (strlen($newPassword) < 6) {
            $message = 'Error: New password must be at least 6 characters long.';
            $messageType = 'error';
        } else {
            try {
                // Get admin ID from session (try both user_id and admin_id)
                $adminId = $_SESSION['admin_id'] ?? $_SESSION['user_id'] ?? null;
                
                if (!$adminId) {
                    $message = 'Error: Unable to identify the current admin user.';
                    $messageType = 'error';
                } else {
                    $user = $pdo->prepare("SELECT password FROM admins WHERE id = ? LIMIT 1");
                    $user->execute([$adminId]);
                    $userData = $user->fetch(PDO::FETCH_ASSOC);
                    
                    if (!$userData || !password_verify($currentPassword, $userData['password'])) {
                        $message = 'Error: Current password is incorrect.';
                        $messageType = 'error';
                    } else {
                        // Update password in admins table
                        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                        $stmt = $pdo->prepare("UPDATE admins SET password = ? WHERE id = ?");
                        $stmt->execute([$hashedPassword, $adminId]);
                        
                        $message = 'Password updated successfully.';
                        $messageType = 'success';
                    }
                }
                
            } catch (Exception $e) {
                $message = 'Error updating password: ' . $e->getMessage();
                $messageType = 'error';
            }
        }
        
    } elseif ($action === 'clear_logs') {
        try {
            $pdo->exec("DELETE FROM system_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)");
            $message = 'Logs older than 30 days have been cleared.';
            $messageType = 'success';
        } catch (Exception $e) {
            $message = 'Error clearing logs: ' . $e->getMessage();
            $messageType = 'error';
        }
    }
}

// Get current settings
$settings = [];
$settingsQuery = $pdo->query("SELECT setting_key, setting_value FROM system_settings");
while ($row = $settingsQuery->fetch(PDO::FETCH_ASSOC)) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

// Get system statistics
$stats = [];
$stats['total_voters'] = $pdo->query("SELECT COUNT(*) FROM voters")->fetchColumn();
$stats['total_candidates'] = $pdo->query("SELECT COUNT(*) FROM candidates")->fetchColumn();
$stats['total_elections'] = $pdo->query("SELECT COUNT(*) FROM elections")->fetchColumn();
$stats['total_votes'] = $pdo->query("SELECT COUNT(*) FROM votes")->fetchColumn();
$stats['total_logs'] = $pdo->query("SELECT COUNT(*) FROM system_logs")->fetchColumn();

// Get recent activity
$recentActivity = $pdo->query("
    SELECT * FROM system_logs 
    ORDER BY created_at DESC 
    LIMIT 10
")->fetchAll();

// Get database size
$dbSize = $pdo->query("
    SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS 'DB Size in MB' 
    FROM information_schema.tables 
    WHERE table_schema = DATABASE()
")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Settings - <?php echo h(get_system_name($pdo)); ?></title>
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
</head>
<body class="bg-gradient-to-br from-slate-50 to-slate-100 min-h-screen">
    <?php include __DIR__ . '/includes/sidebar.php'; ?>
    
    <main class="lg:ml-64 p-4 lg:p-6 pt-16 lg:pt-6 min-h-screen">
            <!-- Header -->
            <div class="mb-8">
                <h1 class="text-3xl font-bold text-slate-800">Admin Settings</h1>
                <p class="text-slate-600 mt-1">Manage system configuration and preferences.</p>
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

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                <!-- System Settings -->
                <div class="lg:col-span-2 space-y-8">
                    <!-- System Configuration -->
                    <div class="bg-white rounded-xl shadow-lg p-6">
                        <div class="flex items-center mb-6">
                            <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center mr-3">
                                <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                </svg>
                            </div>
                            <h2 class="text-xl font-semibold text-slate-800">System Configuration</h2>
                        </div>

                        <form method="post" class="space-y-4">
                            <input type="hidden" name="action" value="update_system">
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-slate-700 mb-1">System Name</label>
                                    <input name="system_name" value="<?php echo htmlspecialchars($settings['system_name'] ?? 'SmartLonda'); ?>" 
                                           placeholder="Enter system name" 
                                           class="w-full border border-slate-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-brand focus:border-transparent">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-slate-700 mb-1">Admin Email</label>
                                    <input type="email" name="admin_email" value="<?php echo htmlspecialchars($settings['admin_email'] ?? ''); ?>" 
                                           placeholder="admin@example.com" 
                                           class="w-full border border-slate-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-brand focus:border-transparent">
                                </div>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1">System Description</label>
                                <textarea name="system_description" rows="3" 
                                          placeholder="Brief description of the voting system" 
                                          class="w-full border border-slate-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-brand focus:border-transparent"><?php echo htmlspecialchars($settings['system_description'] ?? ''); ?></textarea>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-slate-700 mb-1">Timezone</label>
                                    <select name="timezone" class="w-full border border-slate-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-brand focus:border-transparent">
                                        <option value="UTC" <?php echo ($settings['timezone'] ?? '') === 'UTC' ? 'selected' : ''; ?>>UTC</option>
                                        <option value="America/New_York" <?php echo ($settings['timezone'] ?? '') === 'America/New_York' ? 'selected' : ''; ?>>Eastern Time</option>
                                        <option value="America/Chicago" <?php echo ($settings['timezone'] ?? '') === 'America/Chicago' ? 'selected' : ''; ?>>Central Time</option>
                                        <option value="America/Denver" <?php echo ($settings['timezone'] ?? '') === 'America/Denver' ? 'selected' : ''; ?>>Mountain Time</option>
                                        <option value="America/Los_Angeles" <?php echo ($settings['timezone'] ?? '') === 'America/Los_Angeles' ? 'selected' : ''; ?>>Pacific Time</option>
                                        <option value="Europe/London" <?php echo ($settings['timezone'] ?? '') === 'Europe/London' ? 'selected' : ''; ?>>London</option>
                                        <option value="Europe/Paris" <?php echo ($settings['timezone'] ?? '') === 'Europe/Paris' ? 'selected' : ''; ?>>Paris</option>
                                        <option value="Asia/Tokyo" <?php echo ($settings['timezone'] ?? '') === 'Asia/Tokyo' ? 'selected' : ''; ?>>Tokyo</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-slate-700 mb-1">Max Votes per Election</label>
                                    <input type="number" name="max_votes_per_election" min="1" max="10" 
                                           value="<?php echo (int)($settings['max_votes_per_election'] ?? 1); ?>" 
                                           class="w-full border border-slate-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-brand focus:border-transparent">
                                </div>
                            </div>

                            <div class="flex justify-end">
                                <button type="submit" class="bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700 text-white rounded-lg px-6 py-3 font-semibold shadow-lg hover:shadow-xl transition-all duration-200">
                                    Save Settings
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- Change Password -->
                    <div class="bg-white rounded-xl shadow-lg p-6">
                        <div class="flex items-center mb-6">
                            <div class="w-10 h-10 bg-red-100 rounded-lg flex items-center justify-center mr-3">
                                <svg class="w-5 h-5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                                </svg>
                            </div>
                            <h2 class="text-xl font-semibold text-slate-800">Change Password</h2>
                        </div>

                        <form method="post" class="space-y-4">
                            <input type="hidden" name="action" value="update_password">
                            
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1">Current Password</label>
                                <input type="password" name="current_password" required
                                       placeholder="Enter current password" 
                                       class="w-full border border-slate-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-brand focus:border-transparent">
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-slate-700 mb-1">New Password</label>
                                    <input type="password" name="new_password" required minlength="6"
                                           placeholder="Enter new password" 
                                           class="w-full border border-slate-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-brand focus:border-transparent">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-slate-700 mb-1">Confirm New Password</label>
                                    <input type="password" name="confirm_password" required minlength="6"
                                           placeholder="Confirm new password" 
                                           class="w-full border border-slate-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-brand focus:border-transparent">
                                </div>
                            </div>

                            <div class="flex justify-end">
                                <button type="submit" class="bg-gradient-to-r from-red-600 to-pink-600 hover:from-red-700 hover:to-pink-700 text-white rounded-lg px-6 py-3 font-semibold shadow-lg hover:shadow-xl transition-all duration-200">
                                    Update Password
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Sidebar -->
                <div class="space-y-6">
                    <!-- System Statistics -->
                    <div class="bg-white rounded-xl shadow-lg p-6">
                        <h3 class="text-lg font-semibold text-slate-800 mb-4">System Statistics</h3>
                        <div class="space-y-3">
                            <div class="flex justify-between">
                                <span class="text-sm text-slate-600">Total Voters</span>
                                <span class="font-medium"><?php echo number_format($stats['total_voters']); ?></span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-sm text-slate-600">Total Candidates</span>
                                <span class="font-medium"><?php echo number_format($stats['total_candidates']); ?></span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-sm text-slate-600">Total Elections</span>
                                <span class="font-medium"><?php echo number_format($stats['total_elections']); ?></span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-sm text-slate-600">Total Votes</span>
                                <span class="font-medium"><?php echo number_format($stats['total_votes']); ?></span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-sm text-slate-600">Database Size</span>
                                <span class="font-medium"><?php echo $dbSize; ?> MB</span>
                            </div>
                        </div>
                    </div>

                    <!-- System Maintenance -->
                    <div class="bg-white rounded-xl shadow-lg p-6">
                        <h3 class="text-lg font-semibold text-slate-800 mb-4">System Maintenance</h3>
                        <div class="space-y-3">
                            <form method="post" class="inline">
                                <input type="hidden" name="action" value="clear_logs">
                                <button type="submit" class="w-full text-left px-3 py-2 text-sm text-slate-600 hover:bg-slate-50 rounded-lg transition-colors" 
                                        onclick="return confirm('Are you sure you want to clear old logs?')">
                                    <svg class="w-4 h-4 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                    </svg>
                                    Clear Old Logs
                                </button>
                            </form>
                            
                            <a href="backup_management.php" class="block w-full text-left px-3 py-2 text-sm text-slate-600 hover:bg-slate-50 rounded-lg transition-colors">
                                <svg class="w-4 h-4 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path>
                                </svg>
                                Backup Management
                            </a>
                        </div>
                    </div>

                    <!-- Recent Activity -->
                    <div class="bg-white rounded-xl shadow-lg p-6">
                        <h3 class="text-lg font-semibold text-slate-800 mb-4">Recent Activity</h3>
                        <div class="space-y-3 max-h-64 overflow-y-auto">
                            <?php if (empty($recentActivity)): ?>
                                <p class="text-sm text-slate-500">No recent activity</p>
                            <?php else: ?>
                                <?php foreach ($recentActivity as $activity): ?>
                                    <div class="text-sm">
                                        <div class="font-medium text-slate-900"><?php echo htmlspecialchars($activity['action']); ?></div>
                                        <div class="text-slate-500"><?php echo date('M j, Y g:i A', strtotime($activity['created_at'])); ?></div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </main>
</body>
</html>