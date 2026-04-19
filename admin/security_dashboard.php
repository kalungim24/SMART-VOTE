<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/security_manager.php';
require_once __DIR__ . '/../includes/rate_limiter.php';
require_once __DIR__ . '/../includes/ip_manager.php';
require_once __DIR__ . '/../includes/two_factor_auth.php';
require_once __DIR__ . '/../includes/data_encryption.php';
require_once __DIR__ . '/../includes/audit_logger.php';
require_role('admin');

$security = new SecurityManager($pdo);
$rateLimiter = new RateLimiter($pdo);
$ipManager = new IPManager($pdo, $security);
$twoFactorAuth = new TwoFactorAuth($pdo, $security);
$encryption = new DataEncryption($pdo, $security);
$auditLogger = new AuditLogger($pdo, $security);
$security->setSecurityHeaders();

// Get comprehensive security statistics
$loginStats = $security->getLoginAttemptStats(24);
$securityEvents = $security->getSecurityEventsSummary(24);
$rateLimitStats = $rateLimiter->getStatistics(24);
$ipStats = $ipManager->getStatistics(24);
$encryptionStats = $encryption->getStatistics();
$auditStats = $auditLogger->getAuditStatistics('24 hours');
$twoFactorStats = $twoFactorAuth->getStatistics();

// Get recent security events
$stmt = $pdo->query("
    SELECT event_type, severity, ip_address, user_type, description, created_at 
    FROM security_events 
    ORDER BY created_at DESC 
    LIMIT 20
");
$recentEvents = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get locked out IPs/users
$stmt = $pdo->query("
    SELECT ip_address, username, attempt_type, COUNT(*) as attempts, MAX(attempted_at) as last_attempt
    FROM login_attempts 
    WHERE success = FALSE AND attempted_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE)
    GROUP BY ip_address, username, attempt_type
    HAVING attempts >= 5
    ORDER BY last_attempt DESC
");
$lockedAccounts = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Security Dashboard - SmartVote Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        tailwind.config = { 
            theme: { 
                extend: { 
                    colors: { 
                        brand: '#0ea5e9',
                        primary: '#1e40af'
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
                <div class="flex items-center justify-between">
                    <div>
                        <h1 class="text-3xl font-bold text-slate-800">Security Dashboard</h1>
                        <p class="text-slate-600 mt-1">Monitor security events and system protection</p>
                    </div>
                    <div class="flex items-center space-x-3">
                        <div class="w-12 h-12 bg-red-100 rounded-lg flex items-center justify-center">
                            <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
                            </svg>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Main Content -->
            <div class="space-y-6">
            <!-- Security Status Cards -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                <!-- Failed Logins -->
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center">
                        <div class="w-12 h-12 bg-red-100 rounded-lg flex items-center justify-center mr-4">
                            <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 15.5c-.77.833.192 2.5 1.732 2.5z"></path>
                            </svg>
                        </div>
                        <div>
                            <p class="text-2xl font-bold text-gray-900">
                                <?php 
                                $failedLogins = 0;
                                foreach ($loginStats as $stat) {
                                    if (!$stat['success']) $failedLogins += $stat['count'];
                                }
                                echo $failedLogins;
                                ?>
                            </p>
                            <p class="text-gray-600">Failed Logins (24h)</p>
                        </div>
                    </div>
                </div>

                <!-- Locked Accounts -->
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center">
                        <div class="w-12 h-12 bg-yellow-100 rounded-lg flex items-center justify-center mr-4">
                            <svg class="w-6 h-6 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                            </svg>
                        </div>
                        <div>
                            <p class="text-2xl font-bold text-gray-900"><?php echo count($lockedAccounts); ?></p>
                            <p class="text-gray-600">Locked Accounts</p>
                        </div>
                    </div>
                </div>

                <!-- Security Events -->
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center">
                        <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center mr-4">
                            <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </div>
                        <div>
                            <p class="text-2xl font-bold text-gray-900">
                                <?php 
                                $totalEvents = 0;
                                foreach ($securityEvents as $event) {
                                    $totalEvents += $event['count'];
                                }
                                echo $totalEvents;
                                ?>
                            </p>
                            <p class="text-gray-600">Security Events (24h)</p>
                        </div>
                    </div>
                </div>

                <!-- System Status -->
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center">
                        <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center mr-4">
                            <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </div>
                        <div>
                            <p class="text-2xl font-bold text-green-600">SECURE</p>
                            <p class="text-gray-600">System Status</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Advanced Security Features Grid -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Core Security Features -->
                <div class="bg-white rounded-lg shadow">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h3 class="text-lg font-semibold text-gray-900">Core Security Features</h3>
                    </div>
                    <div class="p-6">
                        <div class="grid grid-cols-1 gap-3">
                            <div class="flex items-center justify-between p-3 bg-green-50 rounded-lg">
                                <div class="flex items-center">
                                    <svg class="w-4 h-4 text-green-600 mr-3" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                                    </svg>
                                    <span class="text-green-800 font-medium text-sm">Brute Force Protection</span>
                                </div>
                                <span class="text-green-600 text-xs font-semibold">ACTIVE</span>
                            </div>
                            <div class="flex items-center justify-between p-3 bg-green-50 rounded-lg">
                                <div class="flex items-center">
                                    <svg class="w-4 h-4 text-green-600 mr-3" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                                    </svg>
                                    <span class="text-green-800 font-medium text-sm">CSRF Protection</span>
                                </div>
                                <span class="text-green-600 text-xs font-semibold">ACTIVE</span>
                            </div>
                            <div class="flex items-center justify-between p-3 bg-green-50 rounded-lg">
                                <div class="flex items-center">
                                    <svg class="w-4 h-4 text-green-600 mr-3" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                                    </svg>
                                    <span class="text-green-800 font-medium text-sm">Rate Limiting</span>
                                </div>
                                <span class="text-green-600 text-xs font-semibold">ACTIVE</span>
                            </div>
                            <div class="flex items-center justify-between p-3 bg-green-50 rounded-lg">
                                <div class="flex items-center">
                                    <svg class="w-4 h-4 text-green-600 mr-3" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                                    </svg>
                                    <span class="text-green-800 font-medium text-sm">Security Headers (HSTS/CSP)</span>
                                </div>
                                <span class="text-green-600 text-xs font-semibold">ACTIVE</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Advanced Security Features -->
                <div class="bg-white rounded-lg shadow">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h3 class="text-lg font-semibold text-gray-900">Advanced Security Layers</h3>
                    </div>
                    <div class="p-6">
                        <div class="grid grid-cols-1 gap-3">
                            <div class="flex items-center justify-between p-3 bg-blue-50 rounded-lg">
                                <div class="flex items-center">
                                    <svg class="w-4 h-4 text-blue-600 mr-3" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                                    </svg>
                                    <span class="text-blue-800 font-medium text-sm">Two-Factor Authentication</span>
                                </div>
                                <span class="text-blue-600 text-xs font-semibold">ACTIVE</span>
                            </div>
                            <div class="flex items-center justify-between p-3 bg-purple-50 rounded-lg">
                                <div class="flex items-center">
                                    <svg class="w-4 h-4 text-purple-600 mr-3" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                                    </svg>
                                    <span class="text-purple-800 font-medium text-sm">IP Management</span>
                                </div>
                                <span class="text-purple-600 text-xs font-semibold">ACTIVE</span>
                            </div>
                            <div class="flex items-center justify-between p-3 bg-indigo-50 rounded-lg">
                                <div class="flex items-center">
                                    <svg class="w-4 h-4 text-indigo-600 mr-3" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                                    </svg>
                                    <span class="text-indigo-800 font-medium text-sm">Data Encryption (AES-256)</span>
                                </div>
                                <span class="text-indigo-600 text-xs font-semibold">ACTIVE</span>
                            </div>
                            <div class="flex items-center justify-between p-3 bg-emerald-50 rounded-lg">
                                <div class="flex items-center">
                                    <svg class="w-4 h-4 text-emerald-600 mr-3" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                                    </svg>
                                    <span class="text-emerald-800 font-medium text-sm">Enhanced Audit Logging</span>
                                </div>
                                <span class="text-emerald-600 text-xs font-semibold">ACTIVE</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Security Statistics Grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                <!-- Rate Limiting Stats -->
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center">
                        <div class="w-12 h-12 bg-orange-100 rounded-lg flex items-center justify-center mr-4">
                            <svg class="w-6 h-6 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </div>
                        <div>
                            <p class="text-2xl font-bold text-gray-900">
                                <?php 
                                echo array_sum(array_column($rateLimitStats, 'total_requests'));
                                ?>
                            </p>
                            <p class="text-gray-600">Rate Limited Requests</p>
                        </div>
                    </div>
                </div>

                <!-- Encryption Stats -->
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center">
                        <div class="w-12 h-12 bg-indigo-100 rounded-lg flex items-center justify-center mr-4">
                            <svg class="w-6 h-6 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                            </svg>
                        </div>
                        <div>
                            <p class="text-2xl font-bold text-gray-900">
                                <?php echo count($encryptionStats['encrypted_fields'] ?? []); ?>
                            </p>
                            <p class="text-gray-600">Encrypted Fields</p>
                        </div>
                    </div>
                </div>

                <!-- 2FA Usage -->
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center">
                        <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center mr-4">
                            <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"></path>
                            </svg>
                        </div>
                        <div>
                            <p class="text-2xl font-bold text-gray-900">
                                <?php 
                                echo array_sum(array_column($twoFactorStats['enabled_by_method'] ?? [], 'count'));
                                ?>
                            </p>
                            <p class="text-gray-600">2FA Enabled Users</p>
                        </div>
                    </div>
                </div>

                <!-- Audit Records -->
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center">
                        <div class="w-12 h-12 bg-emerald-100 rounded-lg flex items-center justify-center mr-4">
                            <svg class="w-6 h-6 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                            </svg>
                        </div>
                        <div>
                            <p class="text-2xl font-bold text-gray-900">
                                <?php 
                                echo array_sum(array_column($auditStats['category_breakdown'] ?? [], 'count'));
                                ?>
                            </p>
                            <p class="text-gray-600">Audit Records (24h)</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Security Events -->
            <div class="bg-white rounded-lg shadow">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-semibold text-gray-900">Recent Security Events</h3>
                </div>
                <div class="overflow-x-auto overflow-y-auto max-h-96">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50 sticky top-0 z-10">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Time</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Event</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Severity</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">IP Address</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Description</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php if (empty($recentEvents)): ?>
                                <tr>
                                    <td colspan="5" class="px-6 py-4 text-center text-gray-500">No recent security events</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($recentEvents as $event): ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <?php echo date('M j, H:i', strtotime($event['created_at'])); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <?php echo htmlspecialchars($event['event_type']); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <?php 
                                        $severityColors = [
                                            'low' => 'bg-green-100 text-green-800',
                                            'medium' => 'bg-yellow-100 text-yellow-800',
                                            'high' => 'bg-red-100 text-red-800',
                                            'critical' => 'bg-red-200 text-red-900'
                                        ];
                                        ?>
                                        <span class="px-2 py-1 text-xs rounded-full <?php echo $severityColors[$event['severity']] ?? 'bg-gray-100 text-gray-800'; ?>">
                                            <?php echo strtoupper($event['severity']); ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?php echo htmlspecialchars($event['ip_address']); ?>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-500">
                                        <?php echo htmlspecialchars($event['description']); ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Locked Accounts -->
            <?php if (!empty($lockedAccounts)): ?>
            <div class="bg-white rounded-lg shadow">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-semibold text-gray-900">Currently Locked Accounts</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">IP Address</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Username</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">User Type</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Failed Attempts</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Last Attempt</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($lockedAccounts as $account): ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <?php echo htmlspecialchars($account['ip_address']); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <?php echo htmlspecialchars($account['username']); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-2 py-1 text-xs rounded-full <?php echo $account['attempt_type'] == 'admin' ? 'bg-purple-100 text-purple-800' : 'bg-blue-100 text-blue-800'; ?>">
                                        <?php echo strtoupper($account['attempt_type']); ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-red-600 font-semibold">
                                    <?php echo $account['attempts']; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?php echo date('M j, H:i', strtotime($account['last_attempt'])); ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>
            </div>
        </main>
</body>
</html>
