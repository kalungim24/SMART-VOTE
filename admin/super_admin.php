<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/security_manager.php';
require_once __DIR__ . '/../includes/activity_logger.php';

require_super_admin();

$security = new SecurityManager($pdo);
$logger = new ActivityLogger($pdo);
$security->setSecurityHeaders();

$message = '';
$messageType = 'success';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!$security->validateCSRFToken($csrfToken)) {
        $message = 'Security token validation failed. Please try again.';
        $messageType = 'error';
        $security->logSecurityEvent('csrf_validation_failed', 'high', ['action' => 'super_admin'], 'CSRF token validation failed for super admin panel');
    } else {
        $action = $_POST['action'] ?? '';
        
        if ($action === 'create_admin') {
            $username = trim($_POST['username'] ?? '');
            $password = $_POST['password'] ?? '';
            $fullname = trim($_POST['fullname'] ?? '');
            $role = trim($_POST['role'] ?? 'admin'); // 'admin' or 'super_admin'
            
            if (empty($username) || empty($password) || empty($fullname)) {
                $message = 'Error: Please fill in all required fields.';
                $messageType = 'error';
            } elseif (strlen($password) < 8) {
                $message = 'Error: Password must be at least 8 characters long.';
                $messageType = 'error';
            } elseif (strlen($username) < 3) {
                $message = 'Error: Username must be at least 3 characters long.';
                $messageType = 'error';
            } else {
                try {
                    // Check if username already exists
                    $existingAdmin = $pdo->prepare("SELECT id FROM admins WHERE username = ?");
                    $existingAdmin->execute([$username]);
                    
                    if ($existingAdmin->fetch()) {
                        $message = 'Error: Username already exists. Please choose a different username.';
                        $messageType = 'error';
                    } else {
                        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                        
                        // Insert new admin
                        $stmt = $pdo->prepare("
                            INSERT INTO admins (username, password, fullname, role, created_at) 
                            VALUES (?, ?, ?, ?, NOW())
                        ");
                        $stmt->execute([$username, $hashedPassword, $fullname, $role]);
                        
                        $newAdminId = $pdo->lastInsertId();
                        
                        $message = "✓ Admin user '$username' created successfully!";
                        $messageType = 'success';
                        
                        // Log activity
                        $logger->log(
                            'admin_created',
                            'admin',
                            'Admin user created from super admin panel',
                            [
                                'description' => "Admin user '$username' created from super admin panel",
                                'target_type' => 'admins',
                                'target_id' => $newAdminId,
                                'target_name' => $username,
                                'status' => 'success',
                                'metadata' => ['username' => $username, 'role' => $role, 'fullname' => $fullname]
                            ]
                        );
                        
                        $security->logSecurityEvent(
                            'admin_created_super_panel',
                            'medium',
                            ['username' => $username, 'role' => $role, 'created_by' => $_SESSION['username'] ?? 'unknown'],
                            "New admin user created: $username"
                        );
                    }
                    
                } catch (Exception $e) {
                    $message = 'Error creating admin user. Please try again.';
                    $messageType = 'error';
                    error_log("Admin creation error: " . $e->getMessage());
                    $security->logSecurityEvent('admin_creation_failed', 'high', ['error' => $e->getMessage()], 'Failed to create admin user');
                }
            }
            
        } elseif ($action === 'delete_admin') {
            $id = (int)($_POST['id'] ?? 0);
            
            // Prevent deleting yourself
            if ($id === (int)($_SESSION['admin_id'] ?? 0)) {
                $message = 'Error: You cannot delete your own account.';
                $messageType = 'error';
            } elseif ($id === 1) {
                $message = 'Error: Cannot delete the system administrator account.';
                $messageType = 'error';
            } else {
                try {
                    // Get admin details before deletion
                    $adminStmt = $pdo->prepare("SELECT username FROM admins WHERE id = ?");
                    $adminStmt->execute([$id]);
                    $adminData = $adminStmt->fetch(PDO::FETCH_ASSOC);
                    
                    $stmt = $pdo->prepare("DELETE FROM admins WHERE id = ?");
                    $stmt->execute([$id]);
                    
                    if ($stmt->rowCount() > 0) {
                        $message = "✓ Admin user '{$adminData['username']}' deleted successfully.";
                        $messageType = 'success';
                        
                        $logger->log(
                            'admin_deleted',
                            'admin',
                            'Admin user deleted from super admin panel',
                            [
                                'description' => "Admin user '{$adminData['username']}' deleted from super admin panel",
                                'target_type' => 'admins',
                                'target_id' => $id,
                                'target_name' => $adminData['username'],
                                'status' => 'success',
                                'metadata' => ['username' => $adminData['username']]
                            ]
                        );
                        
                        $security->logSecurityEvent(
                            'admin_deleted_super_panel',
                            'medium',
                            ['admin_id' => $id, 'username' => $adminData['username'], 'deleted_by' => $_SESSION['username'] ?? 'unknown'],
                            "Admin user deleted: {$adminData['username']}"
                        );
                    } else {
                        $message = 'Error: Admin user not found.';
                        $messageType = 'error';
                    }
                    
                } catch (Exception $e) {
                    $message = 'Error deleting admin user. Please try again.';
                    $messageType = 'error';
                    error_log("Admin deletion error: " . $e->getMessage());
                }
            }
        }
    }
}

// Get admin statistics
try {
    $totalAdmins = $pdo->query("SELECT COUNT(*) FROM admins")->fetchColumn();
    $superAdmins = $pdo->query("SELECT COUNT(*) FROM admins WHERE role = 'super_admin'")->fetchColumn() ?? 0;
    $regularAdmins = $totalAdmins - $superAdmins;
    
    $recentAdmins = $pdo->query("
        SELECT id, username, fullname, role, created_at 
        FROM admins 
        ORDER BY created_at DESC 
        LIMIT 10
    ")->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    $totalAdmins = 0;
    $superAdmins = 0;
    $regularAdmins = 0;
    $recentAdmins = [];
    error_log("Error fetching admin statistics: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Super Admin Panel - SmartVote</title>
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
<body class="bg-gradient-to-br from-slate-900 via-slate-800 to-slate-900 min-h-screen">
    <?php include __DIR__ . '/includes/sidebar.php'; ?>
    
    <main class="lg:ml-64 p-4 lg:p-6 pt-16 lg:pt-6 min-h-screen">
        <!-- Header Section -->
        <div class="mb-8">
            <div class="flex items-center justify-between gap-4 mb-2">
                <div>
                    <h1 class="text-3xl lg:text-4xl font-bold text-white">
                        <span class="bg-gradient-to-r from-purple-400 to-pink-600 bg-clip-text text-transparent">Super Admin Panel</span>
                    </h1>
                    <p class="text-slate-400 mt-2">Create and manage administrator accounts</p>
                </div>
                <div class="hidden sm:flex items-center justify-center w-14 h-14 bg-gradient-to-br from-purple-500 to-pink-500 rounded-2xl shadow-lg">
                    <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"></path>
                    </svg>
                </div>
            </div>
        </div>

        <!-- Alert Messages -->
        <?php if ($message): ?>
            <div class="mb-6 p-4 rounded-xl border backdrop-blur-sm flex items-center gap-3 <?php echo $messageType === 'error' ? 'bg-red-500/10 border-red-500/30 text-red-200' : 'bg-emerald-500/10 border-emerald-500/30 text-emerald-200'; ?>">
                <svg class="w-5 h-5 flex-shrink-0 <?php echo $messageType === 'error' ? 'text-red-400' : 'text-emerald-400'; ?>" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="<?php echo $messageType === 'error' ? 'M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z' : 'M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z'; ?>" clip-rule="evenodd"></path>
                </svg>
                <span><?php echo htmlspecialchars($message); ?></span>
            </div>
        <?php endif; ?>

        <!-- Statistics Dashboard -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-8">
            <!-- Total Admins Card -->
            <div class="bg-gradient-to-br from-blue-500/20 to-blue-600/20 border border-blue-500/30 rounded-xl p-6 backdrop-blur-sm">
                <div class="flex items-center justify-between mb-2">
                    <div class="flex items-center gap-3">
                        <div class="w-12 h-12 bg-blue-500/20 rounded-lg flex items-center justify-center">
                            <svg class="w-6 h-6 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                            </svg>
                        </div>
                        <div>
                            <p class="text-sm text-slate-400">Total Admins</p>
                            <p class="text-2xl font-bold text-blue-300"><?php echo $totalAdmins; ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Super Admins Card -->
            <div class="bg-gradient-to-br from-purple-500/20 to-purple-600/20 border border-purple-500/30 rounded-xl p-6 backdrop-blur-sm">
                <div class="flex items-center justify-between mb-2">
                    <div class="flex items-center gap-3">
                        <div class="w-12 h-12 bg-purple-500/20 rounded-lg flex items-center justify-center">
                            <svg class="w-6 h-6 text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"></path>
                            </svg>
                        </div>
                        <div>
                            <p class="text-sm text-slate-400">Super Admins</p>
                            <p class="text-2xl font-bold text-purple-300"><?php echo $superAdmins; ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Regular Admins Card -->
            <div class="bg-gradient-to-br from-pink-500/20 to-pink-600/20 border border-pink-500/30 rounded-xl p-6 backdrop-blur-sm">
                <div class="flex items-center justify-between mb-2">
                    <div class="flex items-center gap-3">
                        <div class="w-12 h-12 bg-pink-500/20 rounded-lg flex items-center justify-center">
                            <svg class="w-6 h-6 text-pink-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z"></path>
                            </svg>
                        </div>
                        <div>
                            <p class="text-sm text-slate-400">Regular Admins</p>
                            <p class="text-2xl font-bold text-pink-300"><?php echo $regularAdmins; ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Create Admin Form -->
        <div class="bg-slate-800/50 border border-slate-700 rounded-xl backdrop-blur-sm mb-8 shadow-xl">
            <div class="px-6 py-4 border-b border-slate-700">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 bg-gradient-to-br from-green-500 to-emerald-500 rounded-lg flex items-center justify-center">
                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                        </svg>
                    </div>
                    <h2 class="text-xl font-bold text-white">Create New Admin Account</h2>
                </div>
            </div>
            
            <form method="post" class="p-6 space-y-6">
                <input type="hidden" name="csrf_token" value="<?php echo $security->generateCSRFToken(); ?>">
                <input type="hidden" name="action" value="create_admin">

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Username Field -->
                    <div>
                        <label class="block text-sm font-semibold text-slate-200 mb-2">Username *</label>
                        <input required type="text" name="username" minlength="3" maxlength="100"
                               placeholder="Enter username (min 3 characters)" 
                               class="w-full bg-slate-700/50 border border-slate-600 rounded-lg px-4 py-3 text-white placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent transition-all">
                        <p class="text-xs text-slate-400 mt-1">Username must be unique and at least 3 characters</p>
                    </div>

                    <!-- Full Name Field -->
                    <div>
                        <label class="block text-sm font-semibold text-slate-200 mb-2">Full Name *</label>
                        <input required type="text" name="fullname" maxlength="150"
                               placeholder="Enter full name" 
                               class="w-full bg-slate-700/50 border border-slate-600 rounded-lg px-4 py-3 text-white placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent transition-all">
                    </div>
                </div>

                <!-- Password Field -->
                <div>
                    <label class="block text-sm font-semibold text-slate-200 mb-2">Password *</label>
                    <input required type="password" name="password" minlength="8"
                           placeholder="Enter password (min 8 characters)" 
                           class="w-full bg-slate-700/50 border border-slate-600 rounded-lg px-4 py-3 text-white placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent transition-all">
                    <div class="mt-2 p-3 bg-blue-500/10 border border-blue-500/30 rounded-lg">
                        <p class="text-xs text-blue-300">
                            <svg class="w-4 h-4 inline mr-1" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 5v8a2 2 0 01-2 2h-5l-5 4v-4H4a2 2 0 01-2-2V5a2 2 0 012-2h12a2 2 0 012 2z" clip-rule="evenodd"></path></svg>
                            Password must be strong: minimum 8 characters, mix of uppercase, lowercase, numbers
                        </p>
                    </div>
                </div>

                <!-- Role Selection -->
                <div>
                    <label class="block text-sm font-semibold text-slate-200 mb-2">Admin Role *</label>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <!-- Regular Admin -->
                        <label class="relative flex items-start p-4 border border-slate-600 rounded-lg cursor-pointer hover:bg-slate-700/30 transition-all">
                            <input type="radio" name="role" value="admin" checked class="mt-1">
                            <div class="ml-3">
                                <p class="font-medium text-white">Regular Admin</p>
                                <p class="text-sm text-slate-400">Can manage voters, candidates, and elections</p>
                            </div>
                        </label>
                        
                        <!-- Super Admin -->
                        <label class="relative flex items-start p-4 border border-purple-600/50 bg-purple-500/5 rounded-lg cursor-pointer hover:bg-purple-500/10 transition-all">
                            <input type="radio" name="role" value="super_admin" class="mt-1">
                            <div class="ml-3">
                                <p class="font-medium text-white flex items-center gap-2">
                                    Super Admin
                                    <span class="text-xs bg-purple-500 text-white px-2 py-0.5 rounded-full font-bold">ELEVATED</span>
                                </p>
                                <p class="text-sm text-slate-400">Full system access including admin management</p>
                            </div>
                        </label>
                    </div>
                </div>

                <!-- Submit Button -->
                <div class="flex justify-end pt-4">
                    <button type="submit" class="bg-gradient-to-r from-green-500 to-emerald-500 hover:from-green-600 hover:to-emerald-600 text-white font-semibold rounded-lg px-8 py-3 shadow-lg hover:shadow-xl transition-all duration-200 flex items-center gap-2">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                        </svg>
                        Create Admin Account
                    </button>
                </div>
            </form>
        </div>

        <!-- Admin List -->
        <div class="bg-slate-800/50 border border-slate-700 rounded-xl backdrop-blur-sm shadow-xl overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-700">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 bg-gradient-to-br from-blue-500 to-cyan-500 rounded-lg flex items-center justify-center">
                            <svg class="w-5 h-5 text-white" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M13 6a3 3 0 11-6 0 3 3 0 016 0zM18 8a2 2 0 11-4 0 2 2 0 014 0zM14 15a4 4 0 00-8 0v3h8v-3zM6 8a2 2 0 11-4 0 2 2 0 014 0zM16 18v-3a5.972 5.972 0 00-.75-2.906A3.005 3.005 0 0119 15v3h-3zM4.75 12.094A5.973 5.973 0 004 15v3H1v-3a3 3 0 013.75-2.906z"></path>
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-lg font-bold text-white">Admin Accounts</h3>
                            <p class="text-sm text-slate-400">Recent admins created in the system</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full divide-y divide-slate-700">
                    <thead class="bg-slate-700/50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-slate-300 uppercase tracking-wider">ID</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-slate-300 uppercase tracking-wider">Username</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-slate-300 uppercase tracking-wider">Full Name</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-slate-300 uppercase tracking-wider">Role</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-slate-300 uppercase tracking-wider">Created</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-slate-300 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-700">
                        <?php if (!empty($recentAdmins)): ?>
                            <?php foreach ($recentAdmins as $admin): ?>
                                <tr class="hover:bg-slate-700/30 transition-colors <?php echo (int)$admin['id'] === (int)($_SESSION['admin_id'] ?? 0) ? 'bg-blue-500/10' : ''; ?>">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-slate-200">
                                        #<?php echo (int)$admin['id']; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center gap-2">
                                            <span class="text-sm font-medium text-white"><?php echo htmlspecialchars($admin['username']); ?></span>
                                            <?php if ((int)$admin['id'] === (int)($_SESSION['admin_id'] ?? 0)): ?>
                                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-bold bg-blue-500 text-white">You</span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-300">
                                        <?php echo htmlspecialchars($admin['fullname']); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold gap-1 <?php echo ($admin['role'] ?? 'admin') === 'super_admin' ? 'bg-purple-500/20 text-purple-200 border border-purple-500/30' : 'bg-blue-500/20 text-blue-200 border border-blue-500/30'; ?>">
                                            <?php if (($admin['role'] ?? 'admin') === 'super_admin'): ?>
                                                <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"></path></svg>
                                            <?php endif; ?>
                                            <?php echo ucwords(str_replace('_', ' ', $admin['role'] ?? 'admin')); ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-400">
                                        <?php echo date('M j, Y', strtotime($admin['created_at'])); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right">
                                        <div class="flex items-center justify-end gap-2">
                                            <?php if ((int)$admin['id'] !== (int)($_SESSION['admin_id'] ?? 0) && (int)$admin['id'] !== 1): ?>
                                                <form method="post" class="inline" onsubmit="return confirm('Are you sure you want to delete this admin? This action cannot be undone.');">
                                                    <input type="hidden" name="csrf_token" value="<?php echo $security->generateCSRFToken(); ?>">
                                                    <input type="hidden" name="action" value="delete_admin">
                                                    <input type="hidden" name="id" value="<?php echo (int)$admin['id']; ?>">
                                                    <button type="submit" class="inline-flex items-center px-3 py-1.5 text-xs font-medium text-white bg-red-500/80 hover:bg-red-600 rounded-lg transition-all duration-200">
                                                        <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                                        </svg>
                                                        Delete
                                                    </button>
                                                </form>
                                            <?php else: ?>
                                                <span class="inline-flex items-center px-3 py-1.5 text-xs font-medium text-slate-400 bg-slate-600 rounded-lg cursor-not-allowed">
                                                    <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                                                    </svg>
                                                    Protected
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="px-6 py-8 text-center">
                                    <p class="text-slate-400">No admin accounts found</p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="px-6 py-4 border-t border-slate-700 bg-slate-700/20">
                <p class="text-sm text-slate-400">
                    Showing <?php echo min(count($recentAdmins), 10); ?> of <?php echo $totalAdmins; ?> total admin account(s)
                </p>
            </div>
        </div>

        <!-- Help Section -->
        <div class="mt-8 grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- Admin Types Info -->
            <div class="bg-slate-800/50 border border-slate-700 rounded-xl backdrop-blur-sm p-6">
                <div class="flex items-center gap-3 mb-4">
                    <div class="w-10 h-10 bg-blue-500/20 rounded-lg flex items-center justify-center">
                        <svg class="w-5 h-5 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                    <h3 class="text-lg font-bold text-white">Admin Types</h3>
                </div>
                <div class="space-y-3 text-sm text-slate-300">
                    <div>
                        <p class="font-semibold text-white mb-1">Regular Admin</p>
                        <p>Can manage voters, candidates, positions, elections, and view results.</p>
                    </div>
                    <div>
                        <p class="font-semibold text-white mb-1">Super Admin</p>
                        <p>Has full system access including the ability to create and manage other administrators.</p>
                    </div>
                </div>
            </div>

            <!-- Security Tips -->
            <div class="bg-slate-800/50 border border-slate-700 rounded-xl backdrop-blur-sm p-6">
                <div class="flex items-center gap-3 mb-4">
                    <div class="w-10 h-10 bg-yellow-500/20 rounded-lg flex items-center justify-center">
                        <svg class="w-5 h-5 text-yellow-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4v2m0 5v1m7-13a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                    <h3 class="text-lg font-bold text-white">Security Tips</h3>
                </div>
                <ul class="space-y-2 text-sm text-slate-300 list-disc list-inside">
                    <li>Use strong, unique passwords for each admin account</li>
                    <li>Regularly review admin accounts and remove unused ones</li>
                    <li>Limit super admin access to trusted users only</li>
                    <li>Monitor admin activity through the security dashboard</li>
                </ul>
            </div>
        </div>
    </main>
</body>
</html>
