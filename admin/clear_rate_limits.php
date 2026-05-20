<?php
/**
 * Clear Rate Limit Blocks
 * Use this to clear all rate limit blocks (for development/testing)
 * SECURITY: Remove or restrict access to this file in production!
 */

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/db_connect.php';
require_super_admin();

$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        if ($action === 'clear_all') {
            // Clear all rate limit blocks
            $stmt = $pdo->prepare("UPDATE rate_limits SET blocked_until = NULL WHERE blocked_until > NOW()");
            $stmt->execute();
            $count = $stmt->rowCount();
            
            $message = "Successfully cleared {$count} rate limit block(s).";
            $messageType = 'success';
            
        } elseif ($action === 'clear_login') {
            // Clear only login rate limits
            $stmt = $pdo->prepare("UPDATE rate_limits SET blocked_until = NULL WHERE action_type = 'login' AND blocked_until > NOW()");
            $stmt->execute();
            $count = $stmt->rowCount();
            
            $message = "Successfully cleared {$count} login rate limit block(s).";
            $messageType = 'success';
            
        } elseif ($action === 'clear_ip') {
            $ipAddress = trim($_POST['ip_address'] ?? '');
            if ($ipAddress) {
                $stmt = $pdo->prepare("UPDATE rate_limits SET blocked_until = NULL WHERE ip_address = ? AND blocked_until > NOW()");
                $stmt->execute([$ipAddress]);
                $count = $stmt->rowCount();
                
                $message = "Successfully cleared {$count} rate limit block(s) for IP {$ipAddress}.";
                $messageType = 'success';
            } else {
                $message = "Please provide an IP address.";
                $messageType = 'error';
            }
        } elseif ($action === 'clear_all_entries') {
            // Clear all rate limit entries (nuclear option)
            $stmt = $pdo->prepare("DELETE FROM rate_limits");
            $stmt->execute();
            
            $message = "Successfully cleared all rate limit entries.";
            $messageType = 'success';
        }
    } catch (Exception $e) {
        $message = 'Error: ' . $e->getMessage();
        $messageType = 'error';
    }
}

// Get current blocked IPs
$blockedIPs = $pdo->query("
    SELECT DISTINCT ip_address, action_type, blocked_until,
           TIMESTAMPDIFF(SECOND, NOW(), blocked_until) as seconds_remaining
    FROM rate_limits 
    WHERE blocked_until > NOW()
    ORDER BY blocked_until ASC
")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Clear Rate Limits - <?php echo h(get_system_name($pdo)); ?> Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gradient-to-br from-slate-50 to-slate-100 min-h-screen">
    <?php include __DIR__ . '/includes/sidebar.php'; ?>
    
    <main class="lg:ml-64 p-4 lg:p-6 pt-16 lg:pt-6 min-h-screen">
            <div class="mb-8">
                <h1 class="text-3xl font-bold text-slate-800">Clear Rate Limits</h1>
                <p class="text-slate-600 mt-1">Manage rate limit blocks for testing/development</p>
            </div>

            <?php if ($message): ?>
                <div class="mb-6 p-4 rounded-lg border flex items-center <?php echo $messageType === 'error' ? 'bg-red-50 border-red-200 text-red-700' : 'bg-emerald-50 border-emerald-200 text-emerald-700'; ?>">
                    <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                    </svg>
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <div class="bg-white rounded-xl shadow-lg p-6 mb-6">
                <h2 class="text-xl font-semibold text-slate-800 mb-4">Quick Actions</h2>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <!-- Clear All Blocks -->
                    <form method="post" onsubmit="return confirm('Clear all active rate limit blocks?')">
                        <input type="hidden" name="action" value="clear_all">
                        <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-3 px-4 rounded-lg transition-all duration-200">
                            Clear All Active Blocks
                        </button>
                    </form>

                    <!-- Clear Login Blocks Only -->
                    <form method="post" onsubmit="return confirm('Clear all login rate limit blocks?')">
                        <input type="hidden" name="action" value="clear_login">
                        <button type="submit" class="w-full bg-green-600 hover:bg-green-700 text-white font-semibold py-3 px-4 rounded-lg transition-all duration-200">
                            Clear Login Blocks Only
                        </button>
                    </form>
                </div>

                <div class="mt-4 pt-4 border-t border-slate-200">
                    <form method="post" onsubmit="return confirm('Clear ALL rate limit entries (including history)?')">
                        <input type="hidden" name="action" value="clear_all_entries">
                        <button type="submit" class="w-full bg-red-600 hover:bg-red-700 text-white font-semibold py-3 px-4 rounded-lg transition-all duration-200">
                            Clear All Rate Limit Entries (Reset All)
                        </button>
                    </form>
                </div>
            </div>

            <!-- Clear Specific IP -->
            <div class="bg-white rounded-xl shadow-lg p-6 mb-6">
                <h2 class="text-xl font-semibold text-slate-800 mb-4">Clear Specific IP</h2>
                <form method="post" class="flex gap-4">
                    <input type="hidden" name="action" value="clear_ip">
                    <input type="text" name="ip_address" placeholder="Enter IP address (e.g., 127.0.0.1)" 
                           class="flex-1 border border-slate-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white font-semibold py-2 px-6 rounded-lg transition-all duration-200">
                        Clear IP Blocks
                    </button>
                </form>
            </div>

            <!-- Current Blocked IPs -->
            <div class="bg-white rounded-xl shadow-lg overflow-hidden">
                <div class="px-6 py-4 border-b border-slate-200">
                    <h2 class="text-xl font-semibold text-slate-800">Currently Blocked IPs</h2>
                </div>
                <div class="overflow-x-auto">
                    <?php if (empty($blockedIPs)): ?>
                        <div class="p-6 text-center text-slate-500">No active rate limit blocks</div>
                    <?php else: ?>
                        <table class="min-w-full divide-y divide-slate-200">
                            <thead class="bg-slate-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase">IP Address</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase">Action Type</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase">Blocked Until</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase">Time Remaining</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-slate-200">
                                <?php foreach ($blockedIPs as $block): ?>
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-slate-900">
                                            <?php echo htmlspecialchars($block['ip_address']); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-600">
                                            <span class="px-2 py-1 text-xs rounded-full bg-yellow-100 text-yellow-800">
                                                <?php echo htmlspecialchars($block['action_type']); ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-600">
                                            <?php echo date('Y-m-d H:i:s', strtotime($block['blocked_until'])); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-600">
                                            <?php 
                                            $minutes = ceil($block['seconds_remaining'] / 60);
                                            echo $minutes . ' minute(s)';
                                            ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        </main>
</body>
</html>

